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

class TraktTvProvider extends AbstractTvProvider
{
    public function supports(array $record): bool
    {
        return isset($record['show']['ids']['trakt']);
    }

    public function parse(array $record, $user): ?array
    {
        $show = $record['show'] ?? null;
        if (!$show) {
            return null;
        }

        return [
            'title' => $show['title'] ?? 'Unknown',
            'ids' => $this->extractIds($show['ids'] ?? []),
            'year' => $show['year'] ?? null,
            'user' => $user,
            'season' => $record['season'] ?? null,
            'episode' => $record['number'] ?? null,
            'status' => $record['status'] ?? null,
            'rating' => $record['rating'] ?? null,
            'lastWatchedAt' => isset($record['last_watched_at']) ? new \DateTime($record['last_watched_at']) : null,
        ];
    }
}
