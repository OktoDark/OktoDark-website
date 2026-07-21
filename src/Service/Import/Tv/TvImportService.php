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

use App\Domain\EpisodeLifecycleManager;
use App\Domain\SeasonLifecycleManager;
use App\Entity\Episode;
use App\Entity\TV;
use App\Enum\MediaType;
use App\Enum\Source;
use App\Service\Import\Metadata\MetadataHydrator;
use App\Service\Import\Metadata\MetadataLookupService;
use App\Service\Import\Metadata\MetadataMergeService;
use App\Service\Import\Tv\Provider\TvMetadataProviderChain;
use App\Service\Import\Tv\Provider\TvProviderRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TvImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MetadataLookupService $lookup,
        private MetadataMergeService $merge,
        private MetadataHydrator $hydrator,
        private TvHierarchyBuilder $hierarchy,
        private LoggerInterface $log,
        private TvProviderRegistry $registry,
        private TvMetadataProviderChain $metadataChain,
        private ManagerRegistry $doctrine,
        private TvTimeCsvImporter $tvTimeCsv,
        private EpisodeLifecycleManager $episodeLifecycle,
        private SeasonLifecycleManager $seasonLifecycle,
        private TvDuplicateMergeService $duplicateMerge,
    ) {
    }

    /**
     * Imports a single parsed provider record.
     *
     * @return array{status: 'imported'|'skipped'|'invalid', title: string}
     */
    public function import(array $record): array
    {
        /**
         * ⭐ Provider parsing (Trakt, TVTime, Simkl, Generic).
         */
        $provider = $this->registry->resolve($record);
        $parsed = $provider->parse($record, $record['user']);

        if (!$parsed) {
            $this->log->warning('tv.import.invalid_provider_record');

            return ['status' => 'invalid', 'title' => 'Unknown'];
        }

        $title = $parsed['title'] ?? 'Unknown';
        $ids = $parsed['ids'] ?? [];

        $this->log->info('tv.import.start', [
            'title' => $title,
            'ids' => $ids,
        ]);

        /*
         * ⭐ Multi-source discovery (TMDB → TVDB → TVMaze)
         * Resolves every available source by known id or title search, scores
         * them and picks the best one. This is the heart of the importer.
         */
        $meta = $this->lookup->findOrCreateMetadata(
            mediaType: MediaType::TV,
            ids: $ids,
            title: $title,
            year: $parsed['year'] ?? null,
            source: Source::TRAKT,
        );

        $discovery = $this->lookup->discoverTvSources($title, $parsed['year'] ?? null, $ids);

        /*
         * ⭐ Merge metadata per source-priority strategy
         * TMDB first (identity, images, cast), TVDB second (episodes, air dates),
         * TVMaze last (status, schedule); missing fields filled from later sources.
         */
        $this->merge->mergeTvMetadataFromDiscovery($meta, $discovery);

        /**
         * ⭐ Duplicate detection (correct)
         * Because metadata is reused, this works perfectly.
         */
        $existing = $this->em->getRepository(TV::class)->findOneBy([
            'mediaMetadata' => $meta,
            'user' => $parsed['user'],
        ]);

        if ($existing) {
            $this->applyWatchHistory($existing, $parsed);
            $this->applyRatings($existing, $parsed);

            $this->em->flush();

            $this->log->warning('tv.duplicate.merged', [
                'title' => $title,
                'meta' => $meta->getMediaId(),
            ]);

            return ['status' => 'skipped', 'title' => $title];
        }

        /**
         * ⭐ Create TV entity.
         */
        $tv = new TV();
        $tv->setMediaMetadata($meta);
        $tv->setUser($parsed['user']);
        $tv->setStatus(WatchStatus::PLANNING);
        $this->em->persist($tv);

        /*
         * ⭐ Build hierarchy (seasons + episodes)
         * Uses TMDB as the authoritative source, TVMaze/TVDB as episode fallbacks,
         * with TMDB artwork always preferred.
         */
        $this->hierarchy->build($tv, $meta, $discovery, $parsed['user']);

        /*
         * ⭐ Provider-chain enrichment (fallback layer)
         * The primary discovery/merge flow above is authoritative. If any
         * show/season/episode metadata is still missing (overview, image, …)
         * the TvMetadataProviderChain is used as a secondary source so we never
         * ship blank fields when another provider can fill them.
         */
        $this->enrichViaProviderChain($tv, $meta, $ids);

        /*
         * ⭐ Watch history + ratings (provider-specific)
         */
        $this->applyWatchHistory($tv, $parsed);
        $this->applyRatings($tv, $parsed);

        $this->em->flush();

        $this->log->info('tv.import.success', [
            'title' => $title,
            'meta' => $meta->getMediaId(),
        ]);

        return ['status' => 'imported', 'title' => $title];
    }

    /**
     * Streaming import for the web UI.
     *
     * Processes every record and pushes live events (log lines + progress) through
     * the $emit callback so the browser is never blocked without feedback. A record
     * that closes the EntityManager is recovered with a fresh manager so the batch
     * continues instead of poisoning later records.
     *
     * @param UploadedFile[]        $files
     * @param callable(array): void $emit
     *
     * @return array{imported: int, skipped: int, errors: int, files: array<int, string>, logs: array<int, string>}
     */
    public function importStreamed(array $files, $user, callable $emit): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $processedFiles = [];
        $logs = [];

        $total = 0;
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $records = $this->parseFile($file);
                $total += \is_array($records) ? \count($records) : 0;
            }
        }

        $done = 0;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $processedFiles[] = $file->getClientOriginalName();
            $emit(['type' => 'file', 'name' => $file->getClientOriginalName()]);

            $records = $this->parseFile($file);

            if (!$records) {
                $message = "Invalid file: {$file->getClientOriginalName()}";
                $logs[] = $message;
                $emit(['type' => 'log', 'level' => 'error', 'message' => $message]);
                ++$errors;
                continue;
            }

            if ($this->isRawTvTimeExport($records[0] ?? [])) {
                $stats = $this->tvTimeCsv->importFile($file->getRealPath(), $user, $emit, false, null);
                $imported += $stats['episodes'];
                $errors += $stats['errors'];
                continue;
            }

            foreach ($records as $record) {
                if (!$this->em->isOpen()) {
                    $this->em = $this->doctrine->resetManager();
                }

                ++$done;
                $record['user'] = $record['user'] ?? $user;

                try {
                    $result = $this->import($record);
                    $title = $result['title'];

                    if ('imported' === $result['status']) {
                        ++$imported;
                        $emit(['type' => 'log', 'level' => 'success', 'message' => "Imported: {$title}"]);
                    } elseif ('skipped' === $result['status']) {
                        ++$skipped;
                        $emit(['type' => 'log', 'level' => 'warning', 'message' => "Skipped (duplicate): {$title}"]);
                    } else {
                        $emit(['type' => 'log', 'level' => 'error', 'message' => "Invalid record: {$title}"]);
                    }
                } catch (\Throwable $e) {
                    $message = 'Error: '.$e->getMessage();
                    $logs[] = $message;
                    $emit(['type' => 'log', 'level' => 'error', 'message' => $message]);

                    if (!$this->em->isOpen()) {
                        $this->em = $this->doctrine->resetManager();
                    }

                    ++$errors;
                }

                $emit([
                    'type' => 'progress',
                    'done' => $done,
                    'total' => $total,
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ]);
            }
        }

        $emit(['type' => 'log', 'level' => 'info', 'message' => 'Merging duplicate shows…']);

        $merged = $this->duplicateMerge->merge(false);

        $emit([
            'type' => 'log',
            'level' => $merged > 0 ? 'warning' : 'info',
            'message' => "Duplicate merge: {$merged} shows merged.",
        ]);

        $emit([
            'type' => 'done',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'files' => $processedFiles,
        ]);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'files' => $processedFiles,
            'logs' => $logs,
        ];
    }

    /**
     * Detects a raw TVTime export (DynamoDB-style schema, e.g. tracking-prod-records-v2.csv).
     *
     * These files expose columns such as series_name / gsi / key and must be imported
     * via the tracker:migrate-tvtime-csv command. The generic web importer cannot map
     * them and would otherwise create "Unknown" placeholder rows.
     */
    private function isRawTvTimeExport(array $record): bool
    {
        return isset($record['series_name'], $record['gsi']);
    }

    public function importFiles(array $files, array &$logs, $user): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $processedFiles = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $processedFiles[] = $file->getClientOriginalName();

            $records = $this->parseFile($file);

            if (!$records) {
                $logs[] = "Invalid file: {$file->getClientOriginalName()}";
                ++$errors;
                continue;
            }

            if ($this->isRawTvTimeExport($records[0] ?? [])) {
                $rawStats = $this->tvTimeCsv->importFile(
                    $file->getRealPath(),
                    $user,
                    static function (array $event) use (&$logs): void {
                        if ('log' === ($event['type'] ?? '')) {
                            $logs[] = $event['message'] ?? '';
                        }
                    },
                    false,
                    null,
                );
                $imported += $rawStats['episodes'];
                $errors += $rawStats['errors'];
                continue;
            }

            foreach ($records as $record) {
                // A previous record may have closed the EntityManager with a DB
                // error; recover with a fresh manager so the batch can continue.
                if (!$this->em->isOpen()) {
                    $this->em = $this->doctrine->resetManager();
                }

                try {
                    $record['user'] = $record['user'] ?? $user;
                    $status = $this->import($record)['status'];

                    if ('imported' === $status) {
                        ++$imported;
                    } elseif ('skipped' === $status) {
                        ++$skipped;
                    } else {
                        ++$errors;
                    }
                } catch (\Throwable $e) {
                    $logs[] = 'Error: '.$e->getMessage();

                    if (!$this->em->isOpen()) {
                        $this->em = $this->doctrine->resetManager();
                    }

                    ++$errors;
                }
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'files' => $processedFiles,
        ];
    }

    private function parseFile(UploadedFile $file): ?array
    {
        $content = file_get_contents($file->getRealPath());
        $ext = mb_strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'json' => json_decode($content, true),
            'csv' => $this->parseCsv($content),
            default => null,
        };
    }

    private function parseCsv(string $content): array
    {
        $rows = [];
        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines));

        foreach ($lines as $line) {
            if ('' === mb_trim($line)) {
                continue;
            }
            $rows[] = array_combine($headers, str_getcsv($line));
        }

        return $rows;
    }

    /**
     * Secondary enrichment pass using the TvMetadataProviderChain.
     *
     * Primary TMDB/TVDB/TVMaze data is considered authoritative; this pass only
     * backfills empty overview/image fields on the show, its seasons and its
     * episodes. The chain resolves the best available provider (TMDB -> TVDB
      * -> TVMaze) using the external ids carried on the metadata and the parsed ids.
     */
    private function enrichViaProviderChain(TV $tv, MediaMetadata $showMeta, array $ids): void
    {
        if (!$this->metadataChain->isConfigured()) {
            return;
        }

        $chainIds = [
            'tmdb' => $showMeta->getTmdbId(),
            'tvdb' => $showMeta->getExternalId(),
            'tvmaze' => $ids['tvmaze'] ?? null,
        ];

        if ('' === ($chainIds['tmdb'] ?? '')) {
            unset($chainIds['tmdb']);
        }
        if ('' === ($chainIds['tvdb'] ?? '')) {
            unset($chainIds['tvdb']);
        }

        if (null === $showMeta->getImage() || null === $showMeta->getOverview()) {
            $this->metadataChain->enrichShow($showMeta, $chainIds, false);
        }

        foreach ($tv->getSeasons() as $season) {
            $seasonMeta = $season->getMediaMetadata();
            if (!$seasonMeta) {
                continue;
            }

            if (null === $seasonMeta->getImage() || null === $seasonMeta->getOverview()) {
                $this->metadataChain->enrichSeason($showMeta, $seasonMeta, $chainIds, false);
            }

            foreach ($season->getEpisodes() as $episode) {
                $epMeta = $episode->getMediaMetadata();
                if (!$epMeta) {
                    continue;
                }

                if (null === $epMeta->getImage() || null === $epMeta->getOverview()) {
                    $this->metadataChain->enrichEpisode($showMeta, $epMeta, $chainIds, false);
                }
            }
        }
    }

    /**
     * Apply provider-supplied watch history to the imported TV entity.
     *
     * Provider parse() may carry:
     *  - 'season' + 'episode'  → mark that specific episode as watched
     *  - 'status' ('completed' / 'finished') → mark the entire show watched
     *  - 'lastWatchedAt'       → stamp progress/end dates
     *
     * Uses the same EpisodeLifecycleManager path as the UI so progress and
     * status on seasons/TV stay consistent with normal tracking.
     */
    private function applyWatchHistory(TV $tv, array $parsed): void
    {
        $seasonNumber = $parsed['season'] ?? null;
        $episodeNumber = $parsed['episode'] ?? null;
        $status = mb_strtolower((string) ($parsed['status'] ?? ''));
        $lastWatchedAt = $parsed['lastWatchedAt'] ?? null;

        $watchedDates = $lastWatchedAt instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($lastWatchedAt)
            : new \DateTimeImmutable();

        if (null !== $seasonNumber && null !== $episodeNumber) {
            $episode = $this->findEpisode($tv, (int) $seasonNumber, (int) $episodeNumber);

            if ($episode) {
                $episode->setStartDate($episode->getStartDate() ?? $watchedDates);
                $episode->setEndDate($watchedDates);
                $episode->setStatus(\App\Enum\WatchStatus::COMPLETED);
                $episode->setUpdatedAt(new \DateTimeImmutable());

                $season = $episode->getRelatedSeason();
                if ($season) {
                    $this->episodeLifecycle->markEpisodeWatched($episode);
                }

                $this->log->debug('tv.import.watch_history.episode', [
                    'title' => $tv->getMediaMetadata()?->getTitle(),
                    'season' => $seasonNumber,
                    'episode' => $episodeNumber,
                ]);
            }

            return;
        }

        // No specific episode: honour an explicit "completed" status by marking
        // every known episode watched so the show reflects the provider state.
        if (\in_array($status, ['completed', 'finished'], true)) {
            foreach ($tv->getSeasons() as $season) {
                foreach ($season->getEpisodes() as $episode) {
                    if (!$episode->isWatched()) {
                        $episode->setStartDate($episode->getStartDate() ?? $watchedDates);
                        $episode->setEndDate($watchedDates);
                        $episode->setStatus(\App\Enum\WatchStatus::COMPLETED);
                        $episode->setUpdatedAt(new \DateTimeImmutable());
                    }
                }

                $this->seasonLifecycle->updateSeasonAndTv($season);
            }

            $this->log->debug('tv.import.watch_history.completed', [
                'title' => $tv->getMediaMetadata()?->getTitle(),
            ]);
        }
    }

    /**
     * Apply provider-supplied rating to the TV entity.
     *
     * The provider 'rating' (0–10 scale) is stored on the TV-level score field
     * (AbstractMedia::score), matching how the rest of the app records user scores.
     */
    private function applyRatings(TV $tv, array $parsed): void
    {
        $rating = $parsed['rating'] ?? null;

        if (null === $rating) {
            return;
        }

        $rating = (float) $rating;
        if ($rating <= 0) {
            return;
        }

        $tv->setScore((string) $rating);

        $this->log->debug('tv.import.rating', [
            'title' => $tv->getMediaMetadata()?->getTitle(),
            'rating' => $rating,
        ]);
    }

    /**
     * Locate a specific episode by season/episode number within the imported TV.
     */
    private function findEpisode(TV $tv, int $seasonNumber, int $episodeNumber): ?Episode
    {
        foreach ($tv->getSeasons() as $season) {
            if ($seasonNumber !== $season->getSeasonNumber()) {
                continue;
            }

            foreach ($season->getEpisodes() as $episode) {
                if ($episodeNumber === $episode->getEpisodeNumber()) {
                    return $episode;
                }
            }
        }

        return null;
    }
}
