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

class TraktMovieProvider implements MovieImportProviderInterface
{
    public function parse(array $record): ?array
    {
        if (($record['type'] ?? '') !== 'movie') {
            return null;
        }

        $movie = $record['movie'] ?? [];
        $ids = $movie['ids'] ?? [];

        return [
            'title' => $movie['title'] ?? 'Unknown',
            'year' => $movie['year'] ?? null,
            'tmdb_id' => $ids['tmdb'] ?? null,
            'watched_at' => $record['watched_at'] ?? null,
            'status' => !empty($record['watched_at']) ? 'completed' : 'new',
            'progress' => !empty($record['watched_at']) ? 100 : 0,
            'source' => 'trakt',
            'ids' => $ids,
        ];
    }
}
