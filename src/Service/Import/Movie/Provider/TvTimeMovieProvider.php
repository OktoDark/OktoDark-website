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

class TvTimeMovieProvider implements MovieImportProviderInterface
{
    public function parse(array $row): ?array
    {
        if (($row['entity_type'] ?? '') !== 'movie') {
            return null;
        }

        return [
            'title' => $row['movie_name'] ?? 'Unknown',
            'year' => $this->extractYear($row['release_date'] ?? null),
            // TVTime exports runtime in SECONDS; the app stores runtime in minutes.
            'runtime' => $this->runtimeToMinutes($row['runtime'] ?? null),
            'watched_at' => $row['watch_date'] ?? null,
            'status' => !empty($row['watch_date']) ? 'completed' : 'new',
            'progress' => !empty($row['watch_date']) ? 100 : 0,
            'source' => 'tvtime',
            'ids' => [
                'alpha' => $row['alpha_range_key'] ?? null,
            ],
        ];
    }

    private function extractYear(?string $date): ?int
    {
        return $date ? (int) substr($date, 0, 4) : null;
    }

    /**
     * TVTime exports the movie runtime in seconds. Normalize it to whole
     * minutes so it is consistent with the rest of the application, which
     * treats and displays runtime in minutes.
     */
    private function runtimeToMinutes(mixed $seconds): ?int
    {
        $seconds = (int) ($seconds ?? 0);

        if ($seconds <= 0) {
            return null;
        }

        return (int) round($seconds / 60);
    }
}
