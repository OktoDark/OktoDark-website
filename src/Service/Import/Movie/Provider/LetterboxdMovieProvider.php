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

class LetterboxdMovieProvider implements MovieImportProviderInterface
{
    public function parse(array $row): ?array
    {
        if (empty($row['Name'])) {
            return null;
        }

        return [
            'title' => $row['Name'],
            'year' => $row['Year'] ?? null,
            'watched_at' => $row['Watched Date'] ?? null,
            'status' => !empty($row['Watched Date']) ? 'completed' : 'new',
            'progress' => !empty($row['Watched Date']) ? 100 : 0,
            'source' => 'letterboxd',
            'ids' => [],
        ];
    }
}
