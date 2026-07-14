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

use App\Entity\TV;
use App\Enum\MediaType;
use App\Enum\Source;
use App\Service\Import\Metadata\MetadataHydrator;
use App\Service\Import\Metadata\MetadataLookupService;
use App\Service\Import\Metadata\MetadataMergeService;
use App\Service\Import\Tv\Provider\TvProviderRegistry;
use App\Service\TmdbService;
use App\Service\TvMazeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TvImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TmdbService $tmdb,
        private TvMazeService $maze,
        private MetadataLookupService $lookup,
        private MetadataMergeService $merge,
        private MetadataHydrator $hydrator,
        private TvHierarchyBuilder $hierarchy,
        private LoggerInterface $log,
        private TvProviderRegistry $registry,
        private ManagerRegistry $doctrine,
        private TvTimeCsvImporter $tvTimeCsv,
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

        /**
         * ⭐ TMDB‑first identity
         * Ensures:
         * - metadata reuse
         * - no duplicates
         * - consistent linking.
         */
        $meta = $this->lookup->findOrCreateMetadata(
            mediaType: MediaType::TV,
            ids: $ids,                // TMDB ID first, fallback to TVMaze, fallback to provider
            title: $title,
            year: $parsed['year'] ?? null,
            source: Source::TRAKT,
        );

        /**
         * ⭐ Fetch TMDB full show (authoritative).
         */
        $tmdbFull = null;
        if (!empty($ids['tmdb'])) {
            $tmdbFull = $this->tmdb->fetchFullShow((int) $ids['tmdb']);
        }

        /**
         * ⭐ Fetch TVMaze full show (fallback).
         */
        $mazeFull = null;
        if (!empty($ids['tvmaze'])) {
            $mazeFull = $this->fetchMazeFull((int) $ids['tvmaze']);
        }

        /*
         * ⭐ Merge TMDB + TVMaze metadata
         * TMDB always wins if available.
         */
        $this->merge->mergeTvMetadata($meta, $tmdbFull, $mazeFull);

        /**
         * ⭐ Duplicate detection (correct)
         * Because metadata is reused, this works perfectly.
         */
        $existing = $this->em->getRepository(TV::class)->findOneBy([
            'mediaMetadata' => $meta,
            'user' => $parsed['user'],
        ]);

        if ($existing) {
            $this->log->warning('tv.duplicate', [
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
        $this->em->persist($tv);

        /*
         * ⭐ Build hierarchy (seasons + episodes)
         * TvHierarchyBuilder now uses lookup + merge + hydrator internally.
         */
        $this->hierarchy->build($tv, $meta, $tmdbFull, $mazeFull, $parsed['user']);

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
     * via the yamtrack:migrate-tvtime-csv command. The generic web importer cannot map
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

    private function fetchMazeFull(int $mazeId): ?array
    {
        try {
            return [
                'show' => $this->maze->getShow($mazeId),
                'seasons' => $this->maze->getSeasons($mazeId),
                'episodes' => $this->maze->getEpisodes($mazeId),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyWatchHistory(TV $tv, array $parsed): void
    {
        // Provider-specific watch history (Trakt, TVTime, Simkl)
        // You can plug in your logic here.
    }

    private function applyRatings(TV $tv, array $parsed): void
    {
        // Provider-specific ratings
        // You can plug in your logic here.
    }
}
