<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv\Provider;

class SimklTvProvider extends AbstractTvProvider
{
    public function supports(array $record): bool
    {
        return isset($record['simkl_id']);
    }

    public function parse(array $record, $user): ?array
    {
        return [
            'title' => $record['title'] ?? 'Unknown',
            'ids' => [
                'tmdb' => $record['tmdb'] ?? null,
                'tvmaze' => $record['tvmaze'] ?? null,
                'trakt' => $record['trakt'] ?? null,
            ],
            'year' => $record['year'] ?? null,
            'user' => $user,
            'season' => $record['season'] ?? null,
            'episode' => $record['episode'] ?? null,
            'status' => $record['status'] ?? null,
            'rating' => $record['rating'] ?? null,
            'lastWatchedAt' => isset($record['watched_at']) ? new \DateTime($record['watched_at']) : null,
        ];
    }
}
