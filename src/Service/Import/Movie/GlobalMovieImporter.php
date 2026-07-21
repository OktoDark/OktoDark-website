<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Movie;

use App\Entity\User;
use App\Service\Import\Movie\Provider\MovieImportProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GlobalMovieImporter
{
    /**
     * @param MovieImportProviderInterface[] $providers
     */
    public function __construct(
        private array $providers,
        private MovieImportService $importer,
        private EntityManagerInterface $em,
        private ManagerRegistry $doctrine,
        private LoggerInterface $log,
    ) {
    }

    public function importFile(UploadedFile $file, User $user): array
    {
        $rows = $this->parseFile($file);
        $imported = 0;

        foreach ($rows as $row) {
            foreach ($this->providers as $provider) {
                $normalized = $provider->parse($row);

                if ($normalized) {
                    $normalized['user'] = $user;
                    $this->importer->import($normalized);
                    ++$imported;
                    break;
                }
            }
        }

        return ['imported' => $imported];
    }

    /**
     * Streaming import for the web UI.
     *
     * Parses every file up front to learn the total record count, then processes
     * each record and pushes live events (log lines + progress) through the $emit
     * callback so the browser is never blocked without feedback. A record that
     * closes the EntityManager is recovered with a fresh manager so the batch
     * continues instead of poisoning later records.
     *
     * @param UploadedFile[]        $files
     * @param callable(array): void $emit
     *
     * @return array{imported: int, skipped: int, errors: int, files: array<int, string>, logs: array<int, string>}
     */
    public function importStreamed(array $files, ?User $user, callable $emit): array
    {
        $imported = 0;
        $errors = 0;
        $processedFiles = [];
        $logs = [];

        $total = 0;
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $total += \count($this->parseFile($file));
            }
        }

        $done = 0;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $processedFiles[] = $file->getClientOriginalName();
            $emit(['type' => 'file', 'name' => $file->getClientOriginalName()]);

            $rows = $this->parseFile($file);

            if (!$rows) {
                $message = "Invalid file: {$file->getClientOriginalName()}";
                $logs[] = $message;
                $emit(['type' => 'log', 'level' => 'error', 'message' => $message]);
                ++$errors;
                continue;
            }

            foreach ($rows as $row) {
                if (!$this->em->isOpen()) {
                    $this->em = $this->doctrine->resetManager();
                }

                ++$done;

                $matched = false;
                $title = $row['title'] ?? 'Unknown';

                try {
                    foreach ($this->providers as $provider) {
                        $normalized = $provider->parse($row);

                        if ($normalized) {
                            $normalized['user'] = $user;
                            $this->importer->import($normalized);
                            $matched = true;
                            break;
                        }
                    }

                    if ($matched) {
                        ++$imported;
                        $emit(['type' => 'log', 'level' => 'success', 'message' => "Imported: {$title}"]);
                    } else {
                        $message = "Skipped (unrecognized format): {$title}";
                        $logs[] = $message;
                        $emit(['type' => 'log', 'level' => 'warning', 'message' => $message]);
                    }
                } catch (\Throwable $e) {
                    $message = "Error importing {$title}: ".$e->getMessage();
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
                    'skipped' => 0,
                    'errors' => $errors,
                ]);
            }
        }

        $emit([
            'type' => 'done',
            'imported' => $imported,
            'skipped' => 0,
            'errors' => $errors,
            'files' => $processedFiles,
        ]);

        return [
            'imported' => $imported,
            'skipped' => 0,
            'errors' => $errors,
            'files' => $processedFiles,
            'logs' => $logs,
        ];
    }

    private function parseFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());

        // JSON?
        if (str_starts_with(mb_trim($content), '{') || str_starts_with(mb_trim($content), '[')) {
            return json_decode($content, true) ?? [];
        }

        // CSV
        $lines = preg_split('/\r\n|\r|\n/', mb_trim($content));
        $header = str_getcsv(array_shift($lines), ',', '"', '\\');
        $rows = [];

        foreach ($lines as $line) {
            if (!mb_trim($line)) {
                continue;
            }

            $data = str_getcsv($line, ',', '"', '\\');
            $row = [];

            foreach ($header as $i => $col) {
                $row[$col] = $data[$i] ?? null;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
