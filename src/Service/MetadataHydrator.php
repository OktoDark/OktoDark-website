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

class MetadataHydrator
{
    public function __construct(
        private readonly TmdbService $tmdb,
        private readonly TvMazeService $maze,
    ) {
    }

    /**
     * Normalize TMDB raw data into a common metadata array.
     *
     * Expected keys in $tmdbRaw (for TV or Movie):
     *  - id
     *  - name / title
     *  - original_name / original_title
     *  - overview
     *  - genres[]
     *  - runtime / episode_run_time[]
     *  - origin_country[]
     *  - first_air_date / release_date
     *  - poster_path
     *  - backdrop_path
     */
    public function hydrateTmdb(array $tmdbRaw): array
    {
        $isTv = isset($tmdbRaw['name']) || isset($tmdbRaw['first_air_date']);

        $title = $tmdbRaw[$isTv ? 'name' : 'title'] ?? null;
        $originalTitle = $tmdbRaw[$isTv ? 'original_name' : 'original_title'] ?? null;

        $genres = [];
        if (!empty($tmdbRaw['genres']) && \is_array($tmdbRaw['genres'])) {
            $genres = array_map(
                static fn (array $g) => $g['name'] ?? null,
                $tmdbRaw['genres']
            );
            $genres = array_filter($genres);
        }

        // Runtime: TV may have episode_run_time array, movies have runtime
        $runtime = null;
        if (isset($tmdbRaw['runtime'])) {
            $runtime = (int) $tmdbRaw['runtime'];
        } elseif (!empty($tmdbRaw['episode_run_time'][0])) {
            $runtime = (int) $tmdbRaw['episode_run_time'][0];
        }

        // Country
        $country = null;
        if (!empty($tmdbRaw['origin_country'][0])) {
            $country = $tmdbRaw['origin_country'][0];
        }

        // Release date
        $releaseDate = $tmdbRaw[$isTv ? 'first_air_date' : 'release_date'] ?? null;

        // Images (you probably have a helper to build full URLs; here we keep raw paths)
        $image = $tmdbRaw['poster_path'] ?? null;
        $backdrop = $tmdbRaw['backdrop_path'] ?? null;

        return [
            'title' => $title,
            'originalTitle' => $originalTitle,
            'overview' => $tmdbRaw['overview'] ?? null,
            'genres' => $genres,
            'runtime' => $runtime,
            'country' => $country,
            'releaseDate' => $releaseDate,
            'image' => $image,
            'backdrop' => $backdrop,
            'tmdbId' => $tmdbRaw['id'] ?? null,
            // season/episode numbers are episode-level, not show-level; keep null here
            'seasonNumber' => null,
            'episodeNumber' => null,
        ];
    }

    /**
     * Normalize TVMaze raw data into a common metadata array.
     *
     * Expected keys in $mazeRaw:
     *  - id
     *  - name
     *  - summary
     *  - genres[]
     *  - runtime
     *  - premiered
     *  - image['medium'|'original']
     *  - network['country']['code']
     */
    public function hydrateTvMaze(array $mazeRaw): array
    {
        $genres = [];
        if (!empty($mazeRaw['genres']) && \is_array($mazeRaw['genres'])) {
            $genres = $mazeRaw['genres'];
        }

        $country = null;
        if (!empty($mazeRaw['network']['country']['code'])) {
            $country = $mazeRaw['network']['country']['code'];
        }

        $image = null;
        if (!empty($mazeRaw['image']['medium'])) {
            $image = $mazeRaw['image']['medium'];
        } elseif (!empty($mazeRaw['image']['original'])) {
            $image = $mazeRaw['image']['original'];
        }

        return [
            'title' => $mazeRaw['name'] ?? null,
            'originalTitle' => $mazeRaw['name'] ?? null,
            'overview' => $mazeRaw['summary'] ?? null,
            'genres' => $genres,
            'runtime' => isset($mazeRaw['runtime']) ? (int) $mazeRaw['runtime'] : null,
            'country' => $country,
            'releaseDate' => $mazeRaw['premiered'] ?? null,
            'image' => $image,
            'backdrop' => null,
            'mazeId' => $mazeRaw['id'] ?? null,
            'seasonNumber' => null,
            'episodeNumber' => null,
        ];
    }

    /**
     * Legacy helper: hydrate and merge directly into a MediaMetadata entity.
     * Kept for backwards compatibility if you still use it elsewhere.
     */
    public function hydrate(MediaMetadata $meta): MediaMetadata
    {
        $title = $meta->getTitle();
        $year = $meta->getReleaseDate()?->format('Y');
        $type = $meta->getMediaType();

        $tmdbRaw = null;
        $mazeRaw = null;

        // TMDB lookup
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

        $tmdbData = $tmdbRaw ? $this->hydrateTmdb($tmdbRaw) : null;

        // TVMaze lookup (TV only)
        if (MediaType::TV === $type && $meta->getExternalId()) {
            $mazeRaw = $this->maze->getShow((int) $meta->getExternalId());
        }
        $mazeData = $mazeRaw ? $this->hydrateTvMaze($mazeRaw) : null;

        // IDs
        if ($tmdbData && isset($tmdbData['tmdbId'])) {
            $meta->setTmdbId((string) $tmdbData['tmdbId']);
        }
        if ($mazeData && isset($mazeData['mazeId'])) {
            $meta->setExternalId((string) $mazeData['mazeId']);
        }

        // Title
        if ($tmdbData['title'] ?? null) {
            $meta->setTitle($tmdbData['title']);
        } elseif ($mazeData['title'] ?? null) {
            $meta->setTitle($mazeData['title']);
        }

        // Overview
        $meta->setOverview(
            $tmdbData['overview']
            ?? ($mazeData['overview'] ?? null)
            ?? $meta->getOverview()
        );

        // Genres
        $meta->setGenres(
            $tmdbData['genres']
            ?? ($mazeData['genres'] ?? [])
        );

        // Runtime
        $meta->setRuntime(
            $tmdbData['runtime']
            ?? ($mazeData['runtime'] ?? null)
        );

        // Release date
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

        // Image
        $meta->setImage(
            $tmdbData['image']
            ?? ($mazeData['image'] ?? null)
            ?? $meta->getImage()
        );

        // Backdrop
        $meta->setBackdrop(
            $tmdbData['backdrop']
            ?? ($mazeData['backdrop'] ?? null)
            ?? $meta->getBackdrop()
        );

        // Country
        $meta->setCountry(
            $mazeData['country']
            ?? ($tmdbData['country'] ?? null)
            ?? $meta->getCountry()
        );

        // Season / Episode numbers (if present)
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
