<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv;

use App\Domain\SeasonLifecycleManager;
use App\Domain\TvLifecycleManager;
use App\Entity\Episode;
use App\Entity\MediaMetadata;
use App\Entity\Season;
use App\Entity\TV;
use App\Entity\User;
use App\Enum\MediaType;
use App\Enum\Source;
use App\Enum\WatchStatus;
use App\Service\Import\Tv\Provider\TmdbMetadataProvider;
use App\Service\Import\Tv\Provider\TvMetadataProviderChain;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Imports a raw TVTime DynamoDB CSV export (e.g. tracking-prod-records-v2.csv).
 *
 * This is the shared engine behind both the tracker:migrate-tvtime-csv console
 * command and the web TV importer. It maps the TVTime flat schema (watch-episode
 * and user-series rows) into the OktoDark tracking model, building seasons from
 * external metadata providers (TVMaze -> TVDB -> TMDB) and recording watch dates.
 */
class TvTimeCsvImporter
{
    /** @var array<string, bool> "mediaId:season" => true – tracks seasons whose episodes were already built */
    private array $builtSeasons = [];

    /** @var array<string, array<int, string|null>> "series|season" => [episodeNumber => created_at] */
    private array $watched = [];

    /** @var array<string, bool> "series|season" => true – seasons with at least one explicit watch row */
    private array $explicitWatch = [];

    /** @var array<string, array{season:int,episode:int}|null> "series" => resume point */
    private array $followed = [];

    /** @var array<string, \DateTimeInterface|null> "series|season" => watch_date from most_recent_ep_watched */
    private array $resumeWatchDates = [];

    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $registry,
        private TvMetadataProviderChain $metadataProvider,
        private TmdbMetadataProvider $tmdbProvider,
        private SeasonLifecycleManager $seasonLifecycle,
        private TvLifecycleManager $tvLifecycle,
    ) {
    }

    public function loadUser(int $userId): ?User
    {
        return $this->em->getRepository(User::class)->find($userId);
    }

    /**
     * @param callable(array): void $emit Receives streamed events:
     *                                    ['type'=>'log','level'=>string,'message'=>string]
     *                                    ['type'=>'progress','done'=>int,'total'=>int,'imported'=>int,'skipped'=>int,'errors'=>int]
     *
     * @return array{series:int, seasons:int, episodes:int, errors:int}
     */
    public function importFile(string $file, User $user, callable $emit, bool $dryRun = false, ?int $limit = null): array
    {
        // Reset per-run state (guards against re-use within the same process)
        $this->builtSeasons = [];
        $this->watched = [];
        $this->explicitWatch = [];
        $this->followed = [];

        $stats = ['series' => 0, 'seasons' => 0, 'episodes' => 0, 'errors' => 0];
        $userId = $user->getId();

        $cache = [];

        $emit([
            'type' => 'log',
            'level' => 'info',
            'message' => "Importing TVTime export: {$file} (user: {$user->getEmail()})",
        ]);

        $f = new \SplFileObject($file, 'r');
        $f->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $header = null;
        $seriesGroups = [];
        $totalRows = 0;

        // Phase 1: parse and categorize every row, grouped by series
        foreach ($f as $row) {
            if (!\is_array($row)) {
                continue;
            }

            if (null === $header) {
                $header = array_flip(array_map('strtolower', $row));
                continue;
            }

            $json = $this->normalizeRow($row, $header);
            if (!$json) {
                continue;
            }

            $series = $json['series'];
            if (!isset($seriesGroups[$series])) {
                $seriesGroups[$series] = ['series' => null, 'watches' => []];
            }

            if ('series' === $json['type']) {
                $seriesGroups[$series]['series'] = $json;
            } else {
                $seriesGroups[$series]['watches'][] = $json;
            }

            ++$totalRows;
        }

        $total = $totalRows;
        $batchSize = 50;
        $processed = 0;
        $batchCount = 0;

        $emit([
            'type' => 'log',
            'level' => 'info',
            'message' => "Parsed {$totalRows} rows into ".\count($seriesGroups).' series',
        ]);

        // Phase 2: process each series as a correlated unit
        foreach ($seriesGroups as $seriesName => $group) {
            $emit([
                'type' => 'series',
                'name' => $seriesName,
                'status' => 'start',
            ]);

            try {
                $seriesStartStats = $stats;

                if ($group['series']) {
                    $this->importSeriesRow($group['series'], $user, $stats, $cache);
                }

                foreach ($group['watches'] as $watchRow) {
                    $this->importWatchRow($watchRow, $user, $stats, $cache);
                }

                if (!$dryRun && (0 === ++$batchCount % $batchSize)) {
                    $this->em->flush();
                    $this->em->clear();
                    $cache = [];
                    $user = $this->em->getRepository(User::class)->find($userId);
                }

                $processed += ($group['series'] ? 1 : 0) + \count($group['watches']);
                $this->emitProgress($emit, $processed, $total, $stats);

                $emit([
                    'type' => 'series',
                    'name' => $seriesName,
                    'status' => 'done',
                    'seasons' => $stats['seasons'] - $seriesStartStats['seasons'],
                    'episodes' => $stats['episodes'] - $seriesStartStats['episodes'],
                ]);

                if (null !== $limit && $processed >= $limit) {
                    break;
                }
            } catch (\Throwable $e) {
                ++$stats['errors'];
                $emit([
                    'type' => 'log',
                    'level' => 'error',
                    'message' => "Series error ({$seriesName}): {$e->getMessage()}",
                ]);

                $emit([
                    'type' => 'series',
                    'name' => $seriesName,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);

                try {
                    if (!$this->em->isOpen()) {
                        $this->em = $this->registry->resetManager();
                    } else {
                        $this->em->clear();
                    }
                } catch (\Throwable) {
                    $this->em = $this->registry->resetManager();
                }

                $cache = [];
                $user = $this->em->getRepository(User::class)->find($userId);
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $stats;
    }

    private function emitProgress(callable $emit, int $done, int $total, array $stats): void
    {
        $emit([
            'type' => 'progress',
            'done' => $done,
            'total' => $total,
            'imported' => $stats['episodes'],
            'skipped' => 0,
            'errors' => $stats['errors'],
        ]);
    }

    private function normalizeRow(array $row, array $header): ?array
    {
        $key = $row[$header['key'] ?? 0] ?? '';
        $prefix = str_contains($key, '-') ? mb_substr($key, 0, mb_strpos($key, '-')) : $key;

        $tvdbId = isset($header['s_id']) ? (string) mb_trim($row[$header['s_id']] ?? '') : '';
        $tvdbId = '' !== $tvdbId ? $tvdbId : null;

        if (str_contains($row[$header['gsi'] ?? 0] ?? '', 'watch-episode')) {
            $series = mb_trim($row[$header['series_name'] ?? 0] ?? '');

            if ('' === $series && $tvdbId) {
                $series = $this->resolveSeriesNameByTvdbId($tvdbId);
            }

            $season = (int) ($row[$header['s_no'] ?? $header['season_number'] ?? 0] ?? 0);
            $episode = (int) ($row[$header['episode_number'] ?? $header['ep_no'] ?? 0] ?? 0);
            $createdAt = $row[$header['created_at'] ?? 0] ?? null;

            if ('' === $series || $season <= 0 || $episode <= 0) {
                return null;
            }

            return [
                'type' => 'watch',
                'series' => $series,
                'season' => $season,
                'episode' => $episode,
                'created_at' => $createdAt,
                'tvdb_id' => $tvdbId,
            ];
        }

        if ('user' === $prefix) {
            $series = mb_trim($row[$header['series_name'] ?? 0] ?? '');

            if ('' === $series && $tvdbId) {
                $series = $this->resolveSeriesNameByTvdbId($tvdbId);
            }

            if ('' === $series) {
                return null;
            }

            $isFollowed = 'true' === mb_strtolower(mb_trim($row[$header['is_followed'] ?? 0] ?? ''));

            $resume = null;
            $watchDate = null;
            $mru = $row[$header['most_recent_ep_watched'] ?? 0] ?? '';
            if (preg_match('/s_no:(\d+)/', $mru, $ms) && preg_match('/ep_no:(\d+)/', $mru, $me)) {
                $resume = ['season' => (int) $ms[1], 'episode' => (int) $me[1]];
            }

            if (preg_match('/watch_date:([0-9.e+-]+)/', $mru, $md)) {
                $millis = (float) $md[1];
                $seconds = (int) round($millis / 1e6);
                $watchDate = new \DateTimeImmutable("@$seconds");
                $watchDate = $watchDate->setTimezone(new \DateTimeZone('UTC'));
            }

            return [
                'type' => 'series',
                'series' => $series,
                'followed' => $isFollowed,
                'resume' => $resume,
                'watch_date' => $watchDate,
                'tvdb_id' => $tvdbId,
            ];
        }

        return null;
    }

    /**
     * Build an immutable UTC DateTime from a TVTime CSV timestamp.
     *
     * TVTime records watch times in UTC. Parsing explicitly in the UTC zone keeps
     * the stored DATETIME correct regardless of the server/PHP timezone setting,
     * so the same import yields identical rows on any machine.
     */
    private function parseUtc(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === mb_trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert any date to a mutable DateTime in UTC.
     *
     * The Episode.endDate column is mapped as DATETIME_MUTABLE, so Doctrine
     * rejects DateTimeImmutable at conversion time. Normalizing here keeps the
     * stored value explicit and timezone-independent.
     */
    private function toMutableUtc(?\DateTimeInterface $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }

        return new \DateTime($value->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
    }

    private function resolveSeriesNameByTvdbId(string $tvdbId): ?string
    {
        $meta = $this->em->getRepository(MediaMetadata::class)->findOneBy([
            'externalId' => $tvdbId,
            'mediaType' => MediaType::TV,
            'source' => Source::MANUAL,
        ]);

        return $meta ? $meta->getTitle() : null;
    }

    private function importWatchRow(array $json, User $user, array &$stats, array &$cache): void
    {
        $series = $json['series'];
        $seasonNumber = $json['season'];
        $episodeNumber = $json['episode'];
        $createdAt = $json['created_at'];
        $updatedAt = $json['updated_at'] ?? null;
        $tvdbId = $json['tvdb_id'] ?? null;

        $utc = $this->parseUtc($createdAt);
        $updatedUtc = $this->parseUtc($updatedAt) ?: $utc;

        // Record the watch event: keep the EARLIEST created_at (first real watch beats rewatches).
        $watchKey = "{$series}|{$seasonNumber}";
        if (!isset($this->watched[$watchKey][$episodeNumber])) {
            $this->watched[$watchKey][$episodeNumber] = $utc;
        } elseif ($utc && $this->watched[$watchKey][$episodeNumber] instanceof \DateTimeInterface) {
            // Both non-null: keep the earlier timestamp.
            if ($utc < $this->watched[$watchKey][$episodeNumber]) {
                $this->watched[$watchKey][$episodeNumber] = $utc;
            }
        }
        $this->explicitWatch[$watchKey] = true;

        $tvMeta = $this->ensureTvMeta($series, $tvdbId, $cache, $stats);
        $tv = $this->ensureTv($tvMeta, $user, $cache);
        $seasonMeta = $this->ensureSeasonMeta($tvMeta, $tvdbId, $seasonNumber, $cache, $stats);
        $season = $this->ensureSeason($seasonMeta, $tv, $user, $cache);

        $this->ensureSeasonEpisodes($tvMeta, $series, $seasonNumber, $season, $tv, $user, $episodeNumber, $utc, $stats, $cache, $updatedUtc);
    }

    private function importSeriesRow(array $json, User $user, array &$stats, array &$cache): void
    {
        $series = $json['series'];
        $tvdbId = $json['tvdb_id'] ?? null;

        $this->followed[$series] = $json['resume'];

        if ($json['watch_date'] instanceof \DateTimeInterface) {
            $this->resumeWatchDates[$series] = $json['watch_date'];
        }

        $tvMeta = $this->ensureTvMeta($series, $tvdbId, $cache, $stats);
        $tv = $this->ensureTv($tvMeta, $user, $cache);

        // Best-effort: if the user has a resume point and we have no explicit
        // watch records for that season yet, build the season and mark up to
        // the resume episode as watched.
        $resume = $json['resume'];
        if ($resume && empty($this->explicitWatch["{$series}|{$resume['season']}"])) {
            $seasonMeta = $this->ensureSeasonMeta($tvMeta, $tvdbId, $resume['season'], $cache, $stats);
            $season = $this->ensureSeason($seasonMeta, $tv, $user, $cache);

            $showId = $tvMeta->getExternalId();
            if ($showId) {
                $all = $this->metadataProvider->getSeasonEpisodes($showId, $resume['season']);
                foreach ($all as $ep) {
                    if ((int) ($ep['season'] ?? 0) !== $resume['season']) {
                        continue;
                    }
                    $epNum = (int) ($ep['number'] ?? 0);
                    if ($epNum > 0 && $epNum <= $resume['episode']) {
                        if (!isset($this->watched["{$series}|{$resume['season']}"][$epNum])) {
                            $this->watched["{$series}|{$resume['season']}"][$epNum] = $json['watch_date'];
                        }
                    }
                }
                $this->ensureSeasonEpisodes($tvMeta, $series, $resume['season'], $season, $tv, $user, null, $json['watch_date'], $stats, $cache, $json['watch_date']);
            }
        }
    }

    private function ensureTvMeta(string $series, ?string $tvdbId, array &$cache, array &$stats): MediaMetadata
    {
        $metaRepo = $this->em->getRepository(MediaMetadata::class);
        $cacheKey = 'tv:'.mb_strtolower($series);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        // Prefer stable external ID lookup to prevent duplicate TVs on re-import
        // when the stored title was enriched/changed by a metadata provider.
        if ($tvdbId) {
            $tvMeta = $metaRepo->findOneBy([
                'externalId' => $tvdbId,
                'mediaType' => MediaType::TV,
                'source' => Source::MANUAL,
            ]);
        }

        if (!isset($tvMeta) || !$tvMeta) {
            $tvMeta = $metaRepo->findOneBy([
                'title' => $series,
                'mediaType' => MediaType::TV,
                'source' => Source::MANUAL,
            ]);
        }

        if (!$tvMeta) {
            $tvMeta = (new MediaMetadata())
                ->setMediaId(bin2hex(random_bytes(18)))
                ->setSource(Source::MANUAL)
                ->setMediaType(MediaType::TV)
                ->setTitle($series);

            $this->em->persist($tvMeta);
            ++$stats['series'];
        }

        if ($tvdbId) {
            $tvMeta->setExternalId($tvdbId);
            $this->metadataProvider->enrichShow($tvMeta, ['tvdb' => $tvdbId], false);
        } elseif (null === $tvMeta->getExternalId()) {
            $this->metadataProvider->enrichShow($tvMeta);
        } else {
            $this->metadataProvider->enrichShow($tvMeta, [], false);
        }

        if ((!$tvMeta->getImage() || !$tvMeta->getOverview()) && !$tvMeta->getTmdbId()) {
            $tmdbId = $this->tmdbProvider->resolveShowId($series);
            if ($tmdbId) {
                $tvMeta->setTmdbId($tmdbId);
                $this->tmdbProvider->enrichShow($tvMeta, ['tmdb' => $tmdbId], false);
            }
        }

        $cache[$cacheKey] = $tvMeta;

        return $tvMeta;
    }

    private function ensureTv(MediaMetadata $tvMeta, User $user, array &$cache): TV
    {
        $tv = $cache['tv-media:'.$tvMeta->getMediaId()]
            ?? $this->em->getRepository(TV::class)->findOneBy([
                'mediaMetadata' => $tvMeta,
                'user' => $user,
            ]);

        if (!$tv) {
            $tv = (new TV())
                ->setMediaMetadata($tvMeta)
                ->setUser($user)
                ->setStatus(WatchStatus::PLANNING);

            $this->em->persist($tv);
        }
        $cache['tv-media:'.$tvMeta->getMediaId()] = $tv;

        return $tv;
    }

    private function ensureSeasonMeta(MediaMetadata $tvMeta, ?string $tvdbId, int $seasonNumber, array &$cache, array &$stats): MediaMetadata
    {
        $metaRepo = $this->em->getRepository(MediaMetadata::class);

        $seasonMeta = $cache['season-meta:'.$tvMeta->getMediaId().':'.$seasonNumber]
            ?? $metaRepo->findOneBy([
                'mediaId' => $tvMeta->getMediaId(),
                'mediaType' => MediaType::SEASON,
                'seasonNumber' => $seasonNumber,
            ]);

        if (!$seasonMeta) {
            $seasonMeta = (new MediaMetadata())
                ->setMediaId($tvMeta->getMediaId())
                ->setSource(Source::MANUAL)
                ->setMediaType(MediaType::SEASON)
                ->setTitle($tvMeta->getTitle())
                ->setSeasonNumber($seasonNumber);

            $this->em->persist($seasonMeta);
            ++$stats['seasons'];

            $ids = $tvdbId ? ['tvdb' => $tvdbId] : [];
            $this->metadataProvider->enrichSeason($tvMeta, $seasonMeta, $ids);
        } else {
            $ids = $tvdbId ? ['tvdb' => $tvdbId] : [];
            $this->metadataProvider->enrichSeason($tvMeta, $seasonMeta, $ids, false);
        }

        if ((!$seasonMeta->getImage() || !$seasonMeta->getOverview()) && $tvMeta->getTmdbId()) {
            $this->tmdbProvider->enrichSeason($tvMeta, $seasonMeta, ['tmdb' => $tvMeta->getTmdbId()], false);
        }

        $cache['season-meta:'.$tvMeta->getMediaId().':'.$seasonNumber] = $seasonMeta;

        return $seasonMeta;
    }

    private function ensureSeason(MediaMetadata $seasonMeta, TV $tv, User $user, array &$cache): Season
    {
        $season = $cache['season-media:'.$seasonMeta->getMediaId().':'.$seasonMeta->getSeasonNumber()]
            ?? $this->em->getRepository(Season::class)->findOneBy([
                'mediaMetadata' => $seasonMeta,
                'relatedTv' => $tv,
            ]);

        if (!$season) {
            $season = new Season();
            $season->setMediaMetadata($seasonMeta);
            $season->setRelatedTv($tv);
            $season->setUser($user);
            $season->setStatus(WatchStatus::PLANNING);

            $this->em->persist($season);
            $tv->getSeasons()->add($season);
        }
        $cache['season-media:'.$seasonMeta->getMediaId().':'.$seasonMeta->getSeasonNumber()] = $season;

        return $season;
    }

    /**
     * Create the full episode list for a season (from the resolved provider) and
     * mark the watched episodes. Falls back to creating only the watched episode
     * when no external metadata provider matched the show.
     */
    private function ensureSeasonEpisodes(
        MediaMetadata $tvMeta,
        string $series,
        int $seasonNumber,
        Season $season,
        TV $tv,
        User $user,
        ?int $episodeNumber,
        ?\DateTimeInterface $createdAt,
        array &$stats,
        array &$cache,
        ?\DateTimeInterface $updatedAt = null,
    ): void {
        $buildKey = $tvMeta->getMediaId().':'.$seasonNumber;

        if (isset($this->builtSeasons[$buildKey])) {
            // Season already built. Mark this specific newly-watched episode.
            if (null !== $episodeNumber) {
                $this->markEpisodeWatched($tvMeta, $series, $seasonNumber, $episodeNumber, $season, $createdAt, $updatedAt);
                $this->applyStatus($season, $tv, $createdAt ?? $updatedAt);
            }

            return;
        }

        $showId = $tvMeta->getExternalId();

        if (!$showId) {
            // No external provider match → create only the explicitly watched episodes we know about
            $this->builtSeasons[$buildKey] = true;
            foreach ($this->watched["{$series}|{$seasonNumber}"] ?? [] as $epNum => $createdAt) {
                $this->createEpisode($tvMeta, $series, $seasonNumber, (int) $epNum, $season, $user, $createdAt, $stats, $cache, $updatedAt);
            }

            $this->applyStatus($season, $tv, $createdAt ?? $updatedAt);

            return;
        }

        $this->builtSeasons[$buildKey] = true;

        $all = $this->metadataProvider->getSeasonEpisodes($showId, $seasonNumber);
        foreach ($all as $ep) {
            if ((int) ($ep['season'] ?? 0) !== $seasonNumber) {
                continue;
            }
            $epNum = (int) ($ep['number'] ?? 0);
            if ($epNum <= 0) {
                continue;
            }

            $createdAt = $this->watched["{$series}|{$seasonNumber}"][$epNum] ?? null;
            $this->createEpisode($tvMeta, $series, $seasonNumber, $epNum, $season, $user, $createdAt, $stats, $cache, $updatedAt);
        }

        $this->applyStatus($season, $tv, $createdAt ?? $updatedAt);
    }

    private function markEpisodeWatched(
        MediaMetadata $tvMeta,
        string $series,
        int $seasonNumber,
        int $episodeNumber,
        Season $season,
        ?\DateTimeInterface $createdAt,
        ?\DateTimeInterface $updatedAt = null,
    ): void {
        $epMeta = $this->em->getRepository(MediaMetadata::class)->findOneBy([
            'mediaId' => $tvMeta->getMediaId(),
            'mediaType' => MediaType::EPISODE,
            'seasonNumber' => $seasonNumber,
            'episodeNumber' => $episodeNumber,
        ]);

        if (!$epMeta) {
            return;
        }

        $episode = $this->em->getRepository(Episode::class)->findOneBy([
            'mediaMetadata' => $epMeta,
            'relatedSeason' => $season,
        ]);

        if (!$episode) {
            return;
        }

        $isWatched = null !== $createdAt
            || \array_key_exists($episodeNumber, $this->watched["{$series}|{$seasonNumber}"] ?? []);

        if ($isWatched) {
            $watchedDate = $this->watched["{$series}|{$seasonNumber}"][$episodeNumber] ?? $createdAt;

            if (null !== $watchedDate) {
                $episode->setEndDate($this->toMutableUtc($watchedDate));
            } elseif (null === $episode->getEndDate()) {
                $episode->setEndDate(
                    $this->toMutableUtc(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                );
            }

            $episode->setStatus(WatchStatus::COMPLETED);

            $finalUpdatedAt = $updatedAt ?? $watchedDate ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $episode->setUpdatedAt(\DateTimeImmutable::createFromInterface($finalUpdatedAt));

            if (null !== $watchedDate) {
                $episode->setCreatedAt($watchedDate);
            }
        }
    }

    private function applyStatus(Season $season, TV $tv, ?\DateTimeInterface $timestamp = null): void
    {
        // Use the canonical lifecycle managers so imported progress/status
        // match exactly what the rest of the app computes (isWatched-based).
        // updateSeasonAndTv recomputes the season and propagates to the TV.
        $this->seasonLifecycle->updateSeasonAndTv($season);

        if ($timestamp instanceof \DateTimeInterface) {
            $season->setUpdatedAt(\DateTimeImmutable::createFromInterface($timestamp));
            $tv->setUpdatedAt(\DateTimeImmutable::createFromInterface($timestamp));
        }
    }

    private function createEpisode(
        MediaMetadata $tvMeta,
        string $series,
        int $seasonNumber,
        int $episodeNumber,
        Season $season,
        User $user,
        ?\DateTimeInterface $createdAt,
        array &$stats,
        array &$cache,
        ?\DateTimeInterface $updatedAt = null,
    ): void {
        $epMetaKey = 'ep-meta:'.$tvMeta->getMediaId().':'.$seasonNumber.':'.$episodeNumber;
        $epMeta = $cache[$epMetaKey]
            ?? $this->em->getRepository(MediaMetadata::class)->findOneBy([
                'mediaId' => $tvMeta->getMediaId(),
                'mediaType' => MediaType::EPISODE,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);

        if (!$epMeta) {
            $epMeta = (new MediaMetadata())
                ->setMediaId($tvMeta->getMediaId())
                ->setSource(Source::MANUAL)
                ->setMediaType(MediaType::EPISODE)
                ->setTitle($tvMeta->getTitle())
                ->setSeasonNumber($seasonNumber)
                ->setEpisodeNumber($episodeNumber);

            $this->em->persist($epMeta);
            $this->metadataProvider->enrichEpisode($tvMeta, $epMeta);

            if (!$epMeta->getImage() && $tvMeta->getTmdbId()) {
                $this->tmdbProvider->enrichEpisode($tvMeta, $epMeta, ['tmdb' => $tvMeta->getTmdbId()], false);
            }
        }
        $cache[$epMetaKey] = $epMeta;

        $epKey = 'episode:'.$tvMeta->getMediaId().':'.$seasonNumber.':'.$episodeNumber;
        $episode = $cache[$epKey]
            ?? $this->em->getRepository(Episode::class)->findOneBy([
                'mediaMetadata' => $epMeta,
                'relatedSeason' => $season,
            ]);

        if (!$episode) {
            $episode = (new Episode())
                ->setMediaMetadata($epMeta)
                ->setRelatedSeason($season)
                ->setUser($user);

            $this->em->persist($episode);
            $season->getEpisodes()->add($episode);
            ++$stats['episodes'];
        }
        $cache[$epKey] = $episode;

        // An episode is considered watched if it carries an explicit watch
        // timestamp, or if it was recorded as watched via a resume point
        // (stored in the watched set with a null timestamp).
        $isWatched = null !== $createdAt
            || \array_key_exists($episodeNumber, $this->watched["{$series}|{$seasonNumber}"] ?? []);

        if ($isWatched) {
            if (null !== $createdAt) {
                $episode->setEndDate($this->toMutableUtc($createdAt));
            } elseif (null === $episode->getEndDate()) {
                $episode->setEndDate(
                    $this->toMutableUtc(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                );
            }

            $episode->setStatus(WatchStatus::COMPLETED);

            $finalUpdatedAt = $updatedAt ?? $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $episode->setUpdatedAt(\DateTimeImmutable::createFromInterface($finalUpdatedAt));
        }

        if ($isWatched && null !== $createdAt) {
            $episode->setCreatedAt($createdAt);
        }
    }
}
