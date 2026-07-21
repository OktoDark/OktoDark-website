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
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TvMazeService
{
    private HttpClientInterface $client;
    private string $baseUrl = 'https://api.tvmaze.com';

    public function __construct(
        HttpClientInterface $client,
        private CacheInterface $cache,
    ) {
        $this->client = $client;
    }

    // ---------------------------------------------------------
    // SAFE WRAPPER
    // ---------------------------------------------------------
    private function safe(callable $fn): ?array
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeGet(string $url): ?array
    {
        $key = 'tvmaze_'.md5($url);

        return $this->cache->get($key, function () use ($url): ?array {
            try {
                return $this->client->request('GET', $url)->toArray();
            } catch (\Throwable) {
                return null;
            }
        });
    }

    // ---------------------------------------------------------
    // SEARCH (list, used by multi-source discovery)
    // ---------------------------------------------------------
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchShowList(string $title, ?int $year): array
    {
        if ('' === mb_trim($title)) {
            return [];
        }

        $data = $this->safeGet($this->baseUrl.'/search/shows?q='.urlencode($title)) ?? [];

        $shows = [];
        foreach ($data as $item) {
            $show = $item['show'] ?? null;
            if ($show) {
                $shows[] = $show;
            }
        }

        return $shows;
    }

    /**
     * Extract the normalized external id set from a raw TVMaze show record.
     *
     * @param array<string, mixed> $show
     */
    public function extractExternalIds(array $show): Import\Metadata\Structure\ExternalIds
    {
        return new Import\Metadata\Structure\ExternalIds(
            tvmaze: isset($show['id']) ? (string) $show['id'] : null,
            tvdb: $show['externals']['thetvdb'] ?? null,
        );
    }

    // ---------------------------------------------------------
    // SEARCH SHOWS
    // ---------------------------------------------------------
    public function searchShows(string $query): array
    {
        if ('' === mb_trim($query)) {
            return [];
        }

        $data = $this->safeGet($this->baseUrl.'/search/shows?q='.urlencode($query)) ?? [];

        $results = [];

        foreach ($data as $item) {
            $show = $item['show'] ?? null;
            if (!$show) {
                continue;
            }

            $results[] = [
                'metaId' => $show['id'],
                'name' => $show['name'],
                'year' => isset($show['premiered'])
                    ? mb_substr($show['premiered'], 0, 4)
                    : null,
                'image' => $show['image']['medium'] ?? '',
            ];
        }

        return $results;
    }

    // ---------------------------------------------------------
    // SHOW DETAILS
    // ---------------------------------------------------------
    public function getShow(int $mazeId): ?array
    {
        $show = $this->safeGet($this->baseUrl.'/shows/'.$mazeId);

        return $show ? $this->hydrateShowMetadata($show) : null;
    }

    // ---------------------------------------------------------
    // SEASONS
    // ---------------------------------------------------------
    public function getSeasons(int $mazeId): array
    {
        $data = $this->safeGet($this->baseUrl.'/shows/'.$mazeId.'/seasons') ?? [];

        return array_map(fn ($s) => $this->hydrateSeasonMetadata($s), $data);
    }

    // ---------------------------------------------------------
    // EPISODES
    // ---------------------------------------------------------
    public function getEpisodes(int $mazeId): array
    {
        $data = $this->safeGet($this->baseUrl.'/shows/'.$mazeId.'/episodes') ?? [];

        return array_map(fn ($e) => $this->hydrateEpisodeMetadata($e), $data);
    }

    public function getSeasonEpisodes(int $seasonId): array
    {
        $data = $this->safeGet($this->baseUrl.'/seasons/'.$seasonId.'/episodes') ?? [];

        return array_map(fn ($e) => $this->hydrateEpisodeMetadata($e), $data);
    }

    // ---------------------------------------------------------
    // NEXT EPISODE
    // ---------------------------------------------------------
    public function getNextEpisode(int $mazeId): ?array
    {
        $show = $this->safeGet($this->baseUrl.'/shows/'.$mazeId);

        $next = $show['next_episode'] ?? null;

        return $next ? $this->hydrateEpisodeMetadata($next) : null;
    }

    // ---------------------------------------------------------
    // CAST
    // ---------------------------------------------------------
    public function getCast(int $mazeId): array
    {
        return $this->safeGet($this->baseUrl.'/shows/'.$mazeId.'/cast') ?? [];
    }

    // ---------------------------------------------------------
    // FULL SHOW (SHOW + SEASONS + EPISODES)
    // ---------------------------------------------------------
    public function fetchFullShow(int $mazeId): ?array
    {
        $show = $this->safeGet($this->baseUrl.'/shows/'.$mazeId);
        if (!$show) {
            return null;
        }

        $seasons = $this->safeGet($this->baseUrl.'/shows/'.$mazeId.'/seasons') ?? [];
        $episodes = $this->safeGet($this->baseUrl.'/shows/'.$mazeId.'/episodes') ?? [];

        $hydratedSeasons = [];
        foreach ($seasons as $season) {
            $seasonNumber = $season['number'] ?? null;
            if (null === $seasonNumber) {
                continue;
            }

            $hydratedSeasons[$seasonNumber] = [
                'metadata' => $this->hydrateSeasonMetadata($season),
                'episodes' => array_map(fn ($ep) => $this->hydrateEpisodeMetadata($ep),
                    array_filter($episodes, static fn ($ep) => $ep['season'] === $seasonNumber)
                ),
            ];
        }

        return [
            'show' => $this->hydrateShowMetadata($show),
            'seasons' => $hydratedSeasons,
        ];
    }

    // ---------------------------------------------------------
    // IMAGE BUILDER
    // ---------------------------------------------------------
    private function buildImageUrl(mixed $image): string
    {
        if (\is_array($image)) {
            return $image['original'] ?? $image['medium'] ?? '';
        }

        if (\is_string($image)) {
            return $image;
        }

        return '';
    }

    // ---------------------------------------------------------
    // HYDRATION
    // ---------------------------------------------------------
    public function hydrateShowMetadata(array $show): array
    {
        $image = $show['image'] ?? null;

        if (\is_string($image)) {
            $imageUrl = $image;
        } elseif (\is_array($image)) {
            $imageUrl = $image['original'] ?? $image['medium'] ?? '';
        } else {
            $imageUrl = '';
        }

        $country = $show['network']['country']['name'] ?? $show['webChannel']['country']['name'] ?? null;

        return [
            'mediaId' => $show['id'] ?? null,
            'externalId' => $show['id'] ?? null,

            'title' => $show['name'] ?? null,
            'overview' => $show['summary'] ?? null,

            'genres' => $show['genres'] ?? [],

            'runtime' => $show['runtime'] ?? null,

            'releaseDate' => $show['premiered'] ?? null,

            'image' => $imageUrl,
            'backdrop' => '',

            'country' => $country,

            'seasonNumber' => null,
            'episodeNumber' => null,
        ];
    }

    public function hydrateSeasonMetadata(array $season): array
    {
        return [
            'mediaId' => $season['id'] ?? null,
            'externalId' => $season['id'] ?? null,

            'title' => $season['name'] ?? null,
            'overview' => $season['summary'] ?? null,

            'genres' => [],

            'runtime' => null,

            'releaseDate' => $season['premiereDate'] ?? null,

            'image' => $this->buildImageUrl($season['image'] ?? []),
            'backdrop' => '',

            'seasonNumber' => $season['number'] ?? null,
            'episodeNumber' => null,
        ];
    }

    public function hydrateEpisodeMetadata(array $ep): array
    {
        return [
            'mediaId' => $ep['id'] ?? null,
            'externalId' => $ep['id'] ?? null,

            'title' => $ep['name'] ?? null,
            'overview' => $ep['summary'] ?? null,

            'genres' => [],

            'runtime' => $ep['runtime'] ?? null,

            'releaseDate' => $ep['airdate'] ?? null,

            'image' => $this->buildImageUrl($ep['image'] ?? []),
            'backdrop' => '',

            'screenshot' => $this->buildImageUrl($ep['image'] ?? []),
            'cast' => [],
            'trailer' => null,

            'seasonNumber' => $ep['season'] ?? null,
            'episodeNumber' => $ep['number'] ?? null,
        ];
    }
}
