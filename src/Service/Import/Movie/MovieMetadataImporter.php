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

use App\Entity\MediaMetadata;

class MovieMetadataImporter
{
    /**
     * Apply provider metadata (TVTime, Trakt, Letterboxd, Simkl, Custom).
     */
    public function apply(MediaMetadata $meta, array $record): void
    {
        // Runtime
        // if (!empty($record['runtime'])) {
        //    $meta->setRuntime((int) $record['runtime']);
        // }

        // Release date
        if (!empty($record['release_date'])) {
            try {
                $meta->setReleaseDate(new \DateTime($record['release_date']));
            } catch (\Throwable) {
                // ignore invalid dates
            }
        }

        // Title override (if provider has better title)
        if (!empty($record['title'])) {
            $meta->setTitle($record['title']);
        }

        // Provider-specific IDs
        if (!empty($record['tmdb_id'])) {
            $meta->setTmdbId((string) $record['tmdb_id']);
        }

        if (!empty($record['ids']['alpha'])) {
            $meta->setMediaId((string) $record['ids']['alpha']);
        }
    }
}
