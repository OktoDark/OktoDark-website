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

class SimklMovieProvider implements MovieImportProviderInterface
{
    public function parse(array $record): ?array
    {
        if (($record['type'] ?? '') !== 'movie') {
            return null;
        }

        return [
            'title' => $record['title'] ?? 'Unknown',
            'year' => $record['year'] ?? null,
            'tmdb_id' => $record['ids']['tmdb'] ?? null,
            'watched_at' => $record['watched_at'] ?? null,
            'status' => !empty($record['watched_at']) ? 'completed' : 'new',
            'progress' => !empty($record['watched_at']) ? 100 : 0,
            'source' => 'simkl',
            'ids' => $record['ids'] ?? [],
        ];
    }
}
