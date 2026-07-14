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
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TVMazeClient
{
    private array $showCache = [];
    private array $seasonCache = [];
    private array $episodeCache = [];

    public function __construct(
        private readonly HttpClientInterface $http,
    ) {
    }

    /**
     * Enrich TV metadata (poster, summary, genres, runtime, premiered).
     */
    public function enrichShowMetadata(MediaMetadata $tvMeta, bool $force = true): void
    {
        // Skip if metadata already exists and force=false
        if (!$force && $tvMeta->getImage() && $tvMeta->getOverview()) {
            return;
        }

        $title = $tvMeta->getTitle();

        // Cached?
        if (isset($this->showCache[$title])) {
            $data = $this->showCache[$title];
        } else {
            try {
                $response = $this->http->request('GET', 'https://api.tvmaze.com/singlesearch/shows', [
                    'query' => ['q' => $title],
                ]);
                $data = $response->toArray(false);
            } catch (\Throwable) {
                return;
            }

            $this->showCache[$title] = $data;
        }

        // Poster
        if (!empty($data['image']['original'] ?? null)) {
            $tvMeta->setImage($data['image']['original']);
        } elseif (!empty($data['image']['medium'] ?? null)) {
            $tvMeta->setImage($data['image']['medium']);
        }

        // Summary
        if (!empty($data['summary'] ?? null)) {
            $tvMeta->setOverview(strip_tags($data['summary']));
        }

        // Genres
        if (!empty($data['genres'] ?? null)) {
            $tvMeta->setGenres($data['genres']);
        }

        // Premiered
        if (!empty($data['premiered'] ?? null)) {
            try {
                $tvMeta->setReleaseDate(new \DateTime($data['premiered']));
            } catch (\Throwable) {
            }
        }

        // Runtime
        if (!empty($data['runtime'] ?? null)) {
            $tvMeta->setRuntime((int) $data['runtime']);
        }

        // Store TVMaze ID
        if (!empty($data['id'] ?? null)) {
            $tvMeta->setExternalId((string) $data['id']);
        }
    }

    /**
     * Enrich Season metadata (poster, summary, airdate).
     */
    public function enrichSeasonMetadata(MediaMetadata $tvMeta, MediaMetadata $seasonMeta, bool $force = true): void
    {
        if (!$force && $seasonMeta->getImage() && $seasonMeta->getOverview()) {
            return;
        }

        $showId = $tvMeta->getExternalId();
        if (!$showId) {
            return;
        }

        // Cached?
        if (isset($this->seasonCache[$showId])) {
            $seasons = $this->seasonCache[$showId];
        } else {
            try {
                $response = $this->http->request('GET', "https://api.tvmaze.com/shows/{$showId}/seasons");
                $seasons = $response->toArray(false);
            } catch (\Throwable) {
                return;
            }

            $this->seasonCache[$showId] = $seasons;
        }

        $seasonNumber = $seasonMeta->getSeasonNumber();

        foreach ($seasons as $season) {
            if ((int) ($season['number'] ?? 0) !== $seasonNumber) {
                continue;
            }

            // Poster
            if (!empty($season['image']['original'] ?? null)) {
                $seasonMeta->setImage($season['image']['original']);
            } elseif (!empty($season['image']['medium'] ?? null)) {
                $seasonMeta->setImage($season['image']['medium']);
            }

            // Summary
            if (!empty($season['summary'] ?? null)) {
                $seasonMeta->setOverview(strip_tags($season['summary']));
            }

            // Airdate
            if (!empty($season['premiereDate'] ?? null)) {
                try {
                    $seasonMeta->setReleaseDate(new \DateTime($season['premiereDate']));
                } catch (\Throwable) {
                }
            }

            break;
        }
    }

    /**
     * Fetch every episode of a show (cached). Returns a flat array of TVMaze
     * episode objects (each with season/number/etc.). Empty array on failure.
     */
    public function getAllEpisodes(string $showId): array
    {
        if (!$showId) {
            return [];
        }

        if (isset($this->episodeCache[$showId])) {
            return $this->episodeCache[$showId];
        }

        try {
            $episodes = $this->http->request('GET', "https://api.tvmaze.com/shows/{$showId}/episodes")
                ->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        $this->episodeCache[$showId] = $episodes;

        return $episodes;
    }

    /**
     * Enrich Episode metadata (still image, summary, runtime, airdate).
     */
    public function enrichEpisodeMetadata(MediaMetadata $tvMeta, MediaMetadata $epMeta, bool $force = true): void
    {
        if (!$force && $epMeta->getImage() && $epMeta->getOverview()) {
            return;
        }

        $showId = $tvMeta->getExternalId();
        if (!$showId) {
            return;
        }

        // Cached?
        if (isset($this->episodeCache[$showId])) {
            $episodes = $this->episodeCache[$showId];
        } else {
            try {
                $response = $this->http->request('GET', "https://api.tvmaze.com/shows/{$showId}/episodes");
                $episodes = $response->toArray(false);
            } catch (\Throwable) {
                return;
            }

            $this->episodeCache[$showId] = $episodes;
        }

        $seasonNumber = $epMeta->getSeasonNumber();
        $episodeNumber = $epMeta->getEpisodeNumber();

        foreach ($episodes as $episode) {
            if ((int) ($episode['season'] ?? 0) !== $seasonNumber) {
                continue;
            }
            if ((int) ($episode['number'] ?? 0) !== $episodeNumber) {
                continue;
            }

            // Still image
            if (!empty($episode['image']['original'] ?? null)) {
                $epMeta->setImage($episode['image']['original']);
            } elseif (!empty($episode['image']['medium'] ?? null)) {
                $epMeta->setImage($episode['image']['medium']);
            }

            // Summary
            if (!empty($episode['summary'] ?? null)) {
                $epMeta->setOverview(strip_tags($episode['summary']));
            }

            // Airdate
            if (!empty($episode['airdate'] ?? null)) {
                try {
                    $epMeta->setReleaseDate(new \DateTime($episode['airdate']));
                } catch (\Throwable) {
                }
            }

            // Runtime
            if (!empty($episode['runtime'] ?? null)) {
                $epMeta->setRuntime((int) $episode['runtime']);
            }

            break;
        }
    }
}
