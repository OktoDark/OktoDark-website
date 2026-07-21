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

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TmdbService
{
    private string $apiKey;
    private HttpClientInterface $client;
    private CacheInterface $cache;

    private const BASE = 'https://api.themoviedb.org/3';
    private const IMG = 'https://image.tmdb.org/t/p/';

    public function __construct(
        HttpClientInterface $client,
        CacheInterface $cache,
        string $tmdbApiKey,
    ) {
        $this->client = $client;
        $this->cache = $cache;
        $this->apiKey = $tmdbApiKey;
    }

    private function safe(callable $fn): ?array
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeGet(string $url, array $params = [], int $ttl = 86400): ?array
    {
        return $this->safe(fn () => $this->get($url, $params, $ttl));
    }

    private function get(string $url, array $params = [], int $ttl = 86400): ?array
    {
        $cacheKey = 'tmdb_'.md5($url.json_encode($params));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($url, $params, $ttl) {
            $item->expiresAfter($ttl);

            try {
                $response = $this->client->request('GET', $url, [
                    'query' => array_merge($params, ['api_key' => $this->apiKey]),
                ]);

                if (200 !== $response->getStatusCode()) {
                    return null;
                }

                return $response->toArray(false);
            } catch (\Throwable) {
                return null;
            }
        });
    }

    // ---------------------------------------------------------
    // MOVIES
    // ---------------------------------------------------------
    public function findMovie(array $ids, ?string $title = null, ?int $year = null): ?array
    {
        return $this->safe(function () use ($ids, $title, $year) {
            if (!empty($ids['tmdb'])) {
                $movie = $this->getMovie($ids['tmdb']);
                if ($movie) {
                    return $movie;
                }
            }

            if ($title) {
                $movie = $this->searchMovie($title, $year);
                if ($movie) {
                    return $movie;
                }
            }

            return null;
        });
    }

    public function getMovie(int $tmdbId): ?array
    {
        return $this->safeGet(self::BASE."/movie/$tmdbId");
    }

    public function searchMovie(string $title, ?int $year): ?array
    {
        $params = ['query' => $title];
        if ($year) {
            $params['year'] = $year;
        }

        $data = $this->safeGet(self::BASE.'/search/movie', $params);

        return $data['results'][0] ?? null;
    }

    // ---------------------------------------------------------
    // DISCOVERY LISTS (New Releases, Popular, Top Rated, Upcoming)
    // ---------------------------------------------------------
    public function discoverMovies(string $list, int $limit = 12, int $ttl = 3600): array
    {
        $allowed = [
            'now_playing' => true,
            'popular' => true,
            'top_rated' => true,
            'upcoming' => true,
        ];

        if (!isset($allowed[$list])) {
            return [];
        }

        $data = $this->safeGet(self::BASE."/movie/$list", ['page' => 1], $ttl);
        $results = $data['results'] ?? [];

        $movies = [];
        foreach (\array_slice($results, 0, $limit) as $item) {
            $movies[] = [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? $item['original_title'] ?? 'Unknown',
                'year' => isset($item['release_date'])
                    ? (int) mb_substr((string) $item['release_date'], 0, 4)
                    : null,
                'poster' => isset($item['poster_path'])
                    ? self::IMG.'w500'.$item['poster_path']
                    : null,
                'rating' => isset($item['vote_average'])
                    ? round((float) $item['vote_average'], 1)
                    : null,
            ];
        }

        return $movies;
    }

    // ---------------------------------------------------------
    // SHOWS
    // ---------------------------------------------------------
    public function findShow(array $ids, ?string $title = null, ?int $year = null): ?array
    {
        return $this->safe(function () use ($ids, $title, $year) {
            if (!empty($ids['tmdb'])) {
                $show = $this->getShow($ids['tmdb']);
                if ($show) {
                    return $show;
                }
            }

            if ($title) {
                $show = $this->searchShow($title, $year);
                if ($show) {
                    return $show;
                }
            }

            return null;
        });
    }

    public function getShow(int $tmdbId): ?array
    {
        return $this->safeGet(self::BASE."/tv/$tmdbId");
    }

    public function searchShow(string $title, ?int $year): ?array
    {
        $params = ['query' => $title];
        if ($year) {
            $params['first_air_date_year'] = $year;
        }

        $data = $this->safeGet(self::BASE.'/search/tv', $params);

        return $data['results'][0] ?? null;
    }

    // ---------------------------------------------------------
    // SEARCH (list, used by multi-source discovery)
    // ---------------------------------------------------------
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchShowList(string $title, ?int $year): array
    {
        $params = ['query' => $title];
        if ($year) {
            $params['first_air_date_year'] = $year;
        }

        $data = $this->safeGet(self::BASE.'/search/tv', $params);

        return $data['results'] ?? [];
    }

    /**
     * Extract the normalized external id set from a raw TMDB show record.
     *
     * @param array<string, mixed> $show
     */
    public function extractExternalIds(array $show): Import\Metadata\Structure\ExternalIds
    {
        return new Import\Metadata\Structure\ExternalIds(
            tmdb: isset($show['id']) ? (string) $show['id'] : null,
        );
    }

    // ---------------------------------------------------------
    // HYDRATION
    // ---------------------------------------------------------
    public function fetchFullShow(int $tmdbId): ?array
    {
        try {
            // Fetch main show
            $show = $this->getShow($tmdbId);
            if (!$show) {
                return null;
            }

            // Fetch seasons list
            $seasons = $this->getSeasons($tmdbId) ?? [];

            // Fetch episodes for each season
            $fullSeasons = [];

            foreach ($seasons as $season) {
                $seasonNumber = $season['season_number'] ?? null;

                if (null === $seasonNumber) {
                    continue;
                }

                $episodes = $this->getEpisodes($tmdbId, $seasonNumber) ?? [];

                $fullSeasons[$seasonNumber] = [
                    'season' => $season,
                    'episodes' => $episodes,
                ];
            }

            return [
                'show' => $show,
                'seasons' => $fullSeasons,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------
    // SEASONS
    // ---------------------------------------------------------
    public function findSeason(int $showId, int $seasonNumber): ?array
    {
        $season = $this->safeGet(self::BASE."/tv/$showId/season/$seasonNumber");

        if (!$season) {
            return null;
        }

        return [
            'metadata' => $this->hydrateSeasonMetadata($season),
            'episodes' => array_map(fn ($ep) => $this->hydrateEpisodeMetadata($ep), $season['episodes'] ?? []),
        ];
    }

    /**
     * Returns the raw TMDB season list for a show (each item has season_number/id/name).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSeasons(int $tmdbId): array
    {
        return $this->safeGet(self::BASE."/tv/$tmdbId/seasons") ?? [];
    }

    // ---------------------------------------------------------
    // EPISODES
    // ---------------------------------------------------------
    public function findEpisode(int $showId, int $seasonNumber, int $episodeNumber): ?array
    {
        $episode = $this->safeGet(self::BASE."/tv/$showId/season/$seasonNumber/episode/$episodeNumber");

        if (!$episode) {
            return null;
        }

        $hydrated = $this->hydrateEpisodeMetadata($episode);
        $hydrated['cast'] = $this->getEpisodeCredits($showId, $seasonNumber, $episodeNumber);
        $hydrated['trailer'] = $this->getEpisodeVideos($showId, $seasonNumber, $episodeNumber);

        return $hydrated;
    }

    /**
     * Returns the raw TMDB episode list for a given season.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEpisodes(int $tmdbId, int $seasonNumber): array
    {
        $data = $this->safeGet(self::BASE."/tv/$tmdbId/season/$seasonNumber") ?? [];

        return $data['episodes'] ?? [];
    }

    // ---------------------------------------------------------
    // EPISODE CAST (GUEST STARS)
    // ---------------------------------------------------------
    public function getEpisodeCredits(int $showId, int $seasonNumber, int $episodeNumber): array
    {
        $data = $this->safeGet(self::BASE."/tv/$showId/season/$seasonNumber/episode/$episodeNumber/credits");

        $cast = [];
        foreach (\array_slice($data['guest_stars'] ?? [], 0, 12) as $person) {
            $cast[] = [
                'name' => $person['name'] ?? null,
                'character' => $person['character'] ?? null,
                'image' => isset($person['profile_path'])
                    ? self::IMG.'w185'.$person['profile_path']
                    : null,
            ];
        }

        return $cast;
    }

    // ---------------------------------------------------------
    // EPISODE TRAILER (YOUTUBE EMBED)
    // ---------------------------------------------------------
    public function getEpisodeVideos(int $showId, int $seasonNumber, int $episodeNumber): ?string
    {
        $data = $this->safeGet(self::BASE."/tv/$showId/season/$seasonNumber/episode/$episodeNumber/videos");

        foreach ($data['results'] ?? [] as $video) {
            if (($video['site'] ?? '') === 'YouTube'
                && \in_array($video['type'] ?? '', ['Trailer', 'Teaser'], true)
            ) {
                return 'https://www.youtube.com/embed/'.$video['key'];
            }
        }

        return null;
    }

    // ---------------------------------------------------------
    // SHOW TRAILER (YOUTUBE EMBED)
    // ---------------------------------------------------------
    public function getShowVideos(int $showId): ?string
    {
        $data = $this->safeGet(self::BASE."/tv/$showId/videos");

        foreach ($data['results'] ?? [] as $video) {
            if (($video['site'] ?? '') === 'YouTube'
                && \in_array($video['type'] ?? '', ['Trailer', 'Teaser'], true)
            ) {
                return 'https://www.youtube.com/embed/'.$video['key'];
            }
        }

        return null;
    }

    // ---------------------------------------------------------
    // SHOW CAST (CREDITS)
    // ---------------------------------------------------------
    public function getShowCredits(int $showId): array
    {
        $data = $this->safeGet(self::BASE."/tv/$showId/credits");

        $cast = [];
        foreach (\array_slice($data['cast'] ?? [], 0, 12) as $person) {
            $cast[] = [
                'name' => $person['name'] ?? null,
                'character' => $person['character'] ?? null,
                'image' => isset($person['profile_path'])
                    ? self::IMG.'w185'.$person['profile_path']
                    : null,
            ];
        }

        return $cast;
    }

    // ---------------------------------------------------------
    // SEARCH SHOWS
    // ---------------------------------------------------------
    public function searchShows(?string $query): array
    {
        if (!$query || '' === mb_trim($query)) {
            return [];
        }

        $response = $this->client->request(
            'GET',
            self::BASE.'/search/tv',
            [
                'query' => [
                    'api_key' => $this->apiKey,
                    'query' => $query,
                    'language' => 'en-US',
                ],
            ]
        );

        $data = $response->toArray()['results'] ?? [];

        $results = [];

        foreach ($data as $item) {
            $results[] = [
                'metaId' => $item['id'],
                'name' => $item['name'] ?? $item['original_name'],
                'year' => isset($item['first_air_date'])
                    ? mb_substr($item['first_air_date'], 0, 4)
                    : null,
                'image' => isset($item['poster_path'])
                    ? 'https://image.tmdb.org/t/p/w500'.$item['poster_path']
                    : null,
            ];
        }

        return $results;
    }

    // ---------------------------------------------------------
    // IMAGE BUILDER
    // ---------------------------------------------------------
    private function buildImageUrl(?string $path, string $size = 'w500'): string
    {
        return $path ? self::IMG.$size.$path : '';
    }

    // ---------------------------------------------------------
    // HYDRATION
    // ---------------------------------------------------------
    public function hydrateMetadata(array $data): array
    {
        $title = $data['name'] ?? $data['title'] ?? null;
        $originalTitle = $data['original_name'] ?? $data['original_title'] ?? null;
        $releaseDate = $data['first_air_date'] ?? $data['release_date'] ?? null;
        $posterPath = $data['poster_path'] ?? null;

        $runtime = null;
        if (isset($data['runtime'])) {
            $runtime = $data['runtime'];
        } elseif (isset($data['episode_run_time'][0])) {
            $runtime = $data['episode_run_time'][0];
        }

        $genres = [];
        if (isset($data['genres'])) {
            $genres = array_column($data['genres'], 'name');
        }

        $country = isset($data['origin_country']) ? implode(', ', $data['origin_country']) : null;

        return [
            'mediaId' => $data['id'] ?? null,
            'externalId' => (string) ($data['id'] ?? null),

            'title' => $title,
            'originalTitle' => $originalTitle,
            'overview' => $data['overview'] ?? null,

            'genres' => $genres,

            'runtime' => $runtime,

            'releaseDate' => $releaseDate,

            'image' => $this->buildImageUrl($posterPath),
            'backdrop' => '',

            'screenshot' => $this->buildImageUrl($posterPath),
            'cast' => [],
            'trailer' => null,

            'country' => $country,
        ];
    }

    public function hydrateEpisodeMetadata(array $ep): array
    {
        return [
            'mediaId' => $ep['id'] ?? null,
            'externalId' => $ep['id'] ?? null,

            'title' => $ep['name'] ?? null,
            'overview' => $ep['overview'] ?? null,

            'genres' => [],

            'runtime' => $ep['runtime'] ?? $ep['episode_run_time'] ?? null,

            'releaseDate' => $ep['air_date'] ?? null,

            'image' => $this->buildImageUrl($ep['still_path'] ?? null),
            'backdrop' => '',

            'screenshot' => $this->buildImageUrl($ep['still_path'] ?? null),
            'cast' => [],
            'trailer' => null,

            'seasonNumber' => $ep['season_number'] ?? null,
            'episodeNumber' => $ep['episode_number'] ?? null,
        ];
    }

    public function hydrateSeasonMetadata(array $season): array
    {
        return [
            'mediaId' => $season['id'] ?? null,
            'externalId' => $season['id'] ?? null,

            'title' => $season['name'] ?? null,
            'overview' => $season['overview'] ?? null,

            'genres' => [],

            'runtime' => null,

            'releaseDate' => $season['air_date'] ?? null,

            'image' => $this->buildImageUrl($season['poster_path'] ?? null),
            'backdrop' => '',

            'seasonNumber' => $season['season_number'] ?? null,
            'episodeNumber' => null,
        ];
    }
}
