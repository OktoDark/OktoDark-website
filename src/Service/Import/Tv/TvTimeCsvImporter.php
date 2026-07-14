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

use App\Entity\Episode;
use App\Entity\MediaMetadata;
use App\Entity\Season;
use App\Entity\TV;
use App\Entity\User;
use App\Enum\MediaType;
use App\Enum\Source;
use App\Enum\WatchStatus;
use App\Service\TVMazeClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Imports a raw TVTime DynamoDB CSV export (e.g. tracking-prod-records-v2.csv).
 *
 * This is the shared engine behind both the yamtrack:migrate-tvtime-csv console
 * command and the web TV importer. It maps the TVTime flat schema (watch-episode
 * and user-series rows) into the OktoDark tracking model, building seasons from
 * TVMaze and recording watch dates.
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

    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $registry,
        private TVMazeClient $tvMaze,
    ) {
    }

    public function loadUser(int $userId): ?User
    {
        return $this->em->getRepository(User::class)->find($userId);
    }

    /**
     * @param callable(array): void $emit Receives streamed events:
     *                                   ['type'=>'log','level'=>string,'message'=>string]
     *                                   ['type'=>'progress','done'=>int,'total'=>int,'imported'=>int,'skipped'=>int,'errors'=>int]
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
        $total = max(0, count(file($file)) - 1);

        $batchSize = 50;
        $i = 0;
        $processed = 0;

        foreach ($f as $row) {
            if (!is_array($row)) {
                $this->emitProgress($emit, $processed, $total, $stats);
                continue;
            }

            if (null === $header) {
                $header = array_flip(array_map('strtolower', $row));
                continue;
            }

            $json = $this->normalizeRow($row, $header);
            if (!$json) {
                $this->emitProgress($emit, $processed, $total, $stats);
                continue;
            }

            try {
                if ('watch' === $json['type']) {
                    $this->importWatchRow($json, $user, $stats, $cache);
                } else {
                    $this->importSeriesRow($json, $user, $stats, $cache);
                }

                if (!$dryRun && (0 === ++$i % $batchSize)) {
                    $this->em->flush();
                    $this->em->clear();
                    $cache = [];
                    $user = $this->em->getRepository(User::class)->find($userId);
                }

                if (++$processed === $limit) {
                    break;
                }
            } catch (\Throwable $e) {
                ++$stats['errors'];
                $emit([
                    'type' => 'log',
                    'level' => 'error',
                    'message' => "Row error: {$e->getMessage()}",
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

            $this->emitProgress($emit, $processed, $total, $stats);
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
        $prefix = str_contains($key, '-') ? substr($key, 0, strpos($key, '-')) : $key;

        // Watched episode rows (watch-episode / rewatch-episode gsi)
        if (str_contains($row[$header['gsi'] ?? 0] ?? '', 'watch-episode')) {
            $series = trim($row[$header['series_name'] ?? 0] ?? '');

            // v2 CSV: season_number column is always 1 (TVTime flat schema).
            // Use s_no (col 9) first; fall back to season_number (col 10).
            $season = (int) ($row[$header['s_no'] ?? $header['season_number'] ?? 0] ?? 0);

            // episode_number (col 7) is the canonical field; ep_no (col 11) is a synonym.
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
            ];
        }

        // Followed series rows (user-series-*)
        if ('user' === $prefix) {
            $series = trim($row[$header['series_name'] ?? 0] ?? '');
            if ('' === $series) {
                return null;
            }

            $isFollowed = 'true' === strtolower(trim($row[$header['is_followed'] ?? 0] ?? ''));

            $resume = null;
            $mru = $row[$header['most_recent_ep_watched'] ?? 0] ?? '';
            if (preg_match('/s_no:(\d+)/', $mru, $ms) && preg_match('/ep_no:(\d+)/', $mru, $me)) {
                $resume = ['season' => (int) $ms[1], 'episode' => (int) $me[1]];
            }

            return [
                'type' => 'series',
                'series' => $series,
                'followed' => $isFollowed,
                'resume' => $resume,
            ];
        }

        return null;
    }

    private function importWatchRow(array $json, User $user, array &$stats, array &$cache): void
    {
        $series = $json['series'];
        $seasonNumber = $json['season'];
        $episodeNumber = $json['episode'];
        $createdAt = $json['created_at'];

        // Record the watch event: keep the EARLIEST created_at (first real watch beats rewatches).
        $watchKey = "{$series}|{$seasonNumber}";
        if (!isset($this->watched[$watchKey][$episodeNumber])) {
            $this->watched[$watchKey][$episodeNumber] = $createdAt;
        } elseif ($createdAt && $this->watched[$watchKey][$episodeNumber]) {
            // Both non-null: keep the earlier timestamp.
            if (strtotime($createdAt) < strtotime($this->watched[$watchKey][$episodeNumber])) {
                $this->watched[$watchKey][$episodeNumber] = $createdAt;
            }
        }
        $this->explicitWatch[$watchKey] = true;

        $tvMeta = $this->ensureTvMeta($series, $cache, $stats);
        $tv = $this->ensureTv($tvMeta, $user, $cache);
        $seasonMeta = $this->ensureSeasonMeta($tvMeta, $seasonNumber, $cache, $stats);
        $season = $this->ensureSeason($seasonMeta, $tv, $user, $cache);

        $this->ensureSeasonEpisodes($tvMeta, $series, $seasonNumber, $season, $tv, $user, $episodeNumber, $createdAt, $stats, $cache);
    }

    private function importSeriesRow(array $json, User $user, array &$stats, array &$cache): void
    {
        $series = $json['series'];

        $this->followed[$series] = $json['resume'];

        $tvMeta = $this->ensureTvMeta($series, $cache, $stats);
        $tv = $this->ensureTv($tvMeta, $user, $cache);

        // Best-effort: if the user has a resume point and we have no explicit
        // watch records for that season yet, build the season and mark up to
        // the resume episode as watched.
        $resume = $json['resume'];
        if ($resume && empty($this->explicitWatch["{$series}|{$resume['season']}"])) {
            $seasonMeta = $this->ensureSeasonMeta($tvMeta, $resume['season'], $cache, $stats);
            $season = $this->ensureSeason($seasonMeta, $tv, $user, $cache);

            $showId = $tvMeta->getExternalId();
            if ($showId) {
                $all = $this->tvMaze->getAllEpisodes($showId);
                foreach ($all as $ep) {
                    if ((int) ($ep['season'] ?? 0) !== $resume['season']) {
                        continue;
                    }
                    $epNum = (int) ($ep['number'] ?? 0);
                    if ($epNum > 0 && $epNum <= $resume['episode']) {
                        $this->watched["{$series}|{$resume['season']}"][$epNum] = null;
                    }
                }
                $this->ensureSeasonEpisodes($tvMeta, $series, $resume['season'], $season, $tv, $user, null, null, $stats, $cache);
            }
        }
    }

    private function ensureTvMeta(string $series, array &$cache, array &$stats): MediaMetadata
    {
        $metaRepo = $this->em->getRepository(MediaMetadata::class);

        $tvMeta = $cache['tv:'.strtolower($series)]
            ?? $metaRepo->findOneBy([
                'title' => $series,
                'mediaType' => MediaType::TV,
                'source' => Source::MANUAL,
            ]);

        if (!$tvMeta) {
            $tvMeta = (new MediaMetadata())
                ->setMediaId(bin2hex(random_bytes(18)))
                ->setSource(Source::MANUAL)
                ->setMediaType(MediaType::TV)
                ->setTitle($series);

            $this->em->persist($tvMeta);
            ++$stats['series'];

            $this->tvMaze->enrichShowMetadata($tvMeta);
        } else {
            $this->tvMaze->enrichShowMetadata($tvMeta, false);
        }
        $cache['tv:'.strtolower($series)] = $tvMeta;

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
                ->setStatus(WatchStatus::IN_PROGRESS);

            $this->em->persist($tv);
        }
        $cache['tv-media:'.$tvMeta->getMediaId()] = $tv;

        return $tv;
    }

    private function ensureSeasonMeta(MediaMetadata $tvMeta, int $seasonNumber, array &$cache, array &$stats): MediaMetadata
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

            $this->tvMaze->enrichSeasonMetadata($tvMeta, $seasonMeta);
        } else {
            $this->tvMaze->enrichSeasonMetadata($tvMeta, $seasonMeta, false);
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
            $season->setStatus(WatchStatus::IN_PROGRESS);

            $this->em->persist($season);
        }
        $cache['season-media:'.$seasonMeta->getMediaId().':'.$seasonMeta->getSeasonNumber()] = $season;

        return $season;
    }

    /**
     * Create the full episode list for a season (from TVMaze) and mark the
     * watched episodes. Falls back to creating only the watched episode when
     * TVMaze metadata is unavailable.
     */
    private function ensureSeasonEpisodes(
        MediaMetadata $tvMeta,
        string $series,
        int $seasonNumber,
        Season $season,
        TV $tv,
        User $user,
        ?int $episodeNumber,
        ?string $createdAt,
        array &$stats,
        array &$cache,
    ): void {
        $buildKey = $tvMeta->getMediaId().':'.$seasonNumber;

        if (isset($this->builtSeasons[$buildKey])) {
            // Season already built. Mark this specific newly-watched episode.
            if (null !== $episodeNumber) {
                $this->markEpisodeWatched($tvMeta, $seasonNumber, $episodeNumber, $season, $createdAt);
                $this->applyStatus($season, $tv);
            }

            return;
        }

        $showId = $tvMeta->getExternalId();

        if (!$showId) {
            // No TVMaze match → create only the explicitly watched episodes we know about
            $this->builtSeasons[$buildKey] = true;
            foreach ($this->watched["{$series}|{$seasonNumber}"] ?? [] as $epNum => $createdAt) {
                $this->createEpisode($tvMeta, $series, $seasonNumber, (int) $epNum, $season, $user, $createdAt, $stats, $cache);
            }

            $this->applyStatus($season, $tv);

            return;
        }

        $this->builtSeasons[$buildKey] = true;

        $all = $this->tvMaze->getAllEpisodes($showId);
        foreach ($all as $ep) {
            if ((int) ($ep['season'] ?? 0) !== $seasonNumber) {
                continue;
            }
            $epNum = (int) ($ep['number'] ?? 0);
            if ($epNum <= 0) {
                continue;
            }

            $createdAt = $this->watched["{$series}|{$seasonNumber}"][$epNum] ?? null;
            $this->createEpisode($tvMeta, $series, $seasonNumber, $epNum, $season, $user, $createdAt, $stats, $cache);
        }

        $this->applyStatus($season, $tv);
    }

    private function markEpisodeWatched(
        MediaMetadata $tvMeta,
        int $seasonNumber,
        int $episodeNumber,
        Season $season,
        ?string $createdAt,
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

        if ($createdAt && null === $episode->getEndDate()) {
            try {
                $episode->setEndDate(new \DateTime($createdAt));
            } catch (\Throwable) {
            }
        }
    }

    private function applyStatus(Season $season, TV $tv): void
    {
        $season->setStatus($this->computeSeasonStatus($season));
        $tv->setStatus($this->computeTvStatus($tv));
    }

    private function createEpisode(
        MediaMetadata $tvMeta,
        string $series,
        int $seasonNumber,
        int $episodeNumber,
        Season $season,
        User $user,
        ?string $createdAt,
        array &$stats,
        array &$cache,
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
            $this->tvMaze->enrichEpisodeMetadata($tvMeta, $epMeta);
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
            ++$stats['episodes'];
        }
        $cache[$epKey] = $episode;

        if ($createdAt && null === $episode->getEndDate()) {
            try {
                $episode->setEndDate(new \DateTime($createdAt));
            } catch (\Throwable) {
            }
        }
    }

    private function computeSeasonStatus(Season $season): WatchStatus
    {
        $episodes = $season->getEpisodes();
        $total = $episodes->count();
        if (0 === $total) {
            return WatchStatus::PLANNING;
        }

        $watched = $episodes->filter(fn (Episode $e) => null !== $e->getEndDate());
        $watchedCount = $watched->count();

        $lastWatched = $watched->last();
        $season->setProgress($watchedCount);

        if (0 === $watchedCount) {
            return WatchStatus::PLANNING;
        }
        if ($watchedCount < $total) {
            if ($lastWatched && $lastWatched->getEndDate() < new \DateTime('-45 days')) {
                return WatchStatus::PAUSED;
            }

            return WatchStatus::IN_PROGRESS;
        }

        return WatchStatus::COMPLETED;
    }

    private function computeTvStatus(TV $tv): WatchStatus
    {
        $total = 0;
        $watched = 0;
        $lastWatchedDate = null;

        foreach ($tv->getSeasons() as $season) {
            $episodes = $season->getEpisodes();
            $total += $episodes->count();
            foreach ($episodes as $ep) {
                if (null !== $ep->getEndDate()) {
                    ++$watched;
                    if (null === $lastWatchedDate || $ep->getEndDate() > $lastWatchedDate) {
                        $lastWatchedDate = $ep->getEndDate();
                    }
                }
            }
        }

        if (0 === $total) {
            return WatchStatus::PLANNING;
        }
        if (0 === $watched) {
            return WatchStatus::PLANNING;
        }
        if ($watched < $total) {
            if ($lastWatchedDate && $lastWatchedDate < new \DateTime('-45 days')) {
                return WatchStatus::PAUSED;
            }

            return WatchStatus::IN_PROGRESS;
        }

        return WatchStatus::COMPLETED;
    }
}
