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

class GenericTvProvider extends AbstractTvProvider
{
    public function supports(array $record): bool
    {
        return true; // fallback
    }

    public function parse(array $record, $user): ?array
    {
        return [
            'title' => $record['title'] ?? $record['show']['title'] ?? 'Unknown',
            'ids' => $this->extractIds($record['ids'] ?? $record['show']['ids'] ?? []),
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
