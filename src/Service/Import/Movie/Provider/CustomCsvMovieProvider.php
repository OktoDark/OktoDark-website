<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Movie\Provider;

class CustomCsvMovieProvider implements MovieImportProviderInterface
{
    public function parse(array $row): ?array
    {
        if (empty($row['title'])) {
            return null;
        }

        return [
            'title' => $row['title'],
            'year' => $row['year'] ?? null,
            'runtime' => $row['runtime'] ?? null,
            'watched_at' => $row['watched_at'] ?? null,
            'status' => !empty($row['watched_at']) ? 'completed' : 'new',
            'progress' => !empty($row['watched_at']) ? 100 : 0,
            'source' => 'custom',
            'ids' => [
                'tmdb' => $row['tmdb_id'] ?? null,
            ],
        ];
    }
}
