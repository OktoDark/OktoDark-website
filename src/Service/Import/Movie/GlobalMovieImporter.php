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
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GlobalMovieImporter
{
    /**
     * @param MovieImportProviderInterface[] $providers
     */
    public function __construct(
        private array $providers,
        private MovieImportService $importer,
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
