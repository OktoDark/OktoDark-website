<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Entity\MediaMetadata;
use App\Enum\MediaType;

class MetadataHydrator
{
    public function __construct(
        private readonly TmdbService $tmdb,
        private readonly TvMazeService $maze,
    ) {
    }

    /**
     * Hydrate metadata using TMDB + TVMaze merged.
     */
    public function hydrate(MediaMetadata $meta): MediaMetadata
    {
        $title = $meta->getTitle();
        $year = $meta->getReleaseDate()?->format('Y');
        $type = $meta->getMediaType();

        $tmdbRaw = null;
        $mazeData = null;

        // ---------------------------------------------------------
        // TMDB LOOKUP
        // ---------------------------------------------------------
        if (MediaType::MOVIE === $type) {
            $tmdbRaw = $this->tmdb->findMovie(
                ['tmdb' => $meta->getTmdbId()],
                $title,
                $year
            );
        } elseif (MediaType::TV === $type) {
            $tmdbRaw = $this->tmdb->findShow(
                ['tmdb' => $meta->getTmdbId()],
                $title,
                $year
            );
        }

        $tmdbData = $tmdbRaw ? $this->tmdb->hydrateMetadata($tmdbRaw) : null;

        // ---------------------------------------------------------
        // TVMAZE LOOKUP (TV ONLY)
        // ---------------------------------------------------------
        if (MediaType::TV === $type && $meta->getExternalId()) {
            $mazeData = $this->maze->getShow((int) $meta->getExternalId());
        }

        // ---------------------------------------------------------
        // STORE TMDB ID
        // ---------------------------------------------------------
        if ($tmdbData && isset($tmdbData['externalId'])) {
            $meta->setTmdbId((string) $tmdbData['externalId']);
        }

        // ---------------------------------------------------------
        // STORE TVMAZE ID
        // ---------------------------------------------------------
        if ($mazeData && isset($mazeData['mediaId'])) {
            $meta->setExternalId((string) $mazeData['mediaId']);
        }

        // ---------------------------------------------------------
        // MERGE TITLE
        // ---------------------------------------------------------
        if ($tmdbData['title'] ?? null) {
            $meta->setTitle($tmdbData['title']);
        } elseif ($mazeData['title'] ?? null) {
            $meta->setTitle($mazeData['title']);
        }

        // ---------------------------------------------------------
        // MERGE OVERVIEW
        // ---------------------------------------------------------
        $meta->setOverview(
            $tmdbData['overview']
            ?? ($mazeData['overview'] ?? null)
            ?? $meta->getOverview()
        );

        // ---------------------------------------------------------
        // MERGE GENRES
        // ---------------------------------------------------------
        $meta->setGenres(
            $tmdbData['genres']
            ?? ($mazeData['genres'] ?? [])
        );

        // ---------------------------------------------------------
        // MERGE RUNTIME
        // ---------------------------------------------------------
        $meta->setRuntime(
            $tmdbData['runtime']
            ?? ($mazeData['runtime'] ?? null)
        );

        // ---------------------------------------------------------
        // MERGE RELEASE DATE
        // ---------------------------------------------------------
        if ($tmdbData['releaseDate'] ?? null) {
            try {
                $meta->setReleaseDate(new \DateTime($tmdbData['releaseDate']));
            } catch (\Throwable) {
            }
        } elseif ($mazeData['releaseDate'] ?? null) {
            try {
                $meta->setReleaseDate(new \DateTime($mazeData['releaseDate']));
            } catch (\Throwable) {
            }
        }

        // ---------------------------------------------------------
        // MERGE IMAGE (TMDB preferred)
        // ---------------------------------------------------------
        $meta->setImage(
            $tmdbData['image']
            ?? ($mazeData['image'] ?? null)
            ?? $meta->getImage()
        );

        // ---------------------------------------------------------
        // MERGE BACKDROP (TMDB preferred)
        // ---------------------------------------------------------
        $meta->setBackdrop(
            $tmdbData['backdrop']
            ?? ($mazeData['backdrop'] ?? null)
            ?? $meta->getBackdrop()
        );

        // ---------------------------------------------------------
        // MERGE COUNTRY (TVMaze preferred, TMDB as fallback)
        // ---------------------------------------------------------
        $meta->setCountry(
            $mazeData['country']
            ?? ($tmdbData['country'] ?? null)
            ?? $meta->getCountry()
        );

        // ---------------------------------------------------------
        // MERGE SEASON / EPISODE NUMBERS
        // ---------------------------------------------------------
        if ($tmdbData['seasonNumber'] ?? null) {
            $meta->setSeasonNumber($tmdbData['seasonNumber']);
        } elseif ($mazeData['seasonNumber'] ?? null) {
            $meta->setSeasonNumber($mazeData['seasonNumber']);
        }

        if ($tmdbData['episodeNumber'] ?? null) {
            $meta->setEpisodeNumber($tmdbData['episodeNumber']);
        } elseif ($mazeData['episodeNumber'] ?? null) {
            $meta->setEpisodeNumber($mazeData['episodeNumber']);
        }

        return $meta;
    }
}
