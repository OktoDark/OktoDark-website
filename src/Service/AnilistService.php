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

class AnilistService
{
    private HttpClientInterface $client;
    private CacheInterface $cache;

    private const BASE = 'https://graphql.anilist.co';

    public function __construct(HttpClientInterface $client, CacheInterface $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    private function safe(callable $fn): ?array
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    private function cacheGet(string $key, callable $fn, int $ttl = 3600): ?array
    {
        return $this->cache->get($key, static function (ItemInterface $item) use ($fn, $ttl) {
            $item->expiresAfter($ttl);

            return $fn();
        });
    }

    private function query(string $query, array $variables = [], int $ttl = 3600): ?array
    {
        return $this->safe(function () use ($query, $variables, $ttl) {
            $cacheKey = 'anilist_'.md5($query.json_encode($variables));

            return $this->cacheGet($cacheKey, function () use ($query, $variables) {
                $response = $this->client->request('POST', self::BASE, [
                    'json' => ['query' => $query, 'variables' => $variables],
                ]);

                if (200 !== $response->getStatusCode()) {
                    return null;
                }

                $data = $response->toArray(false);

                return $data['data']['Page']['media'] ?? null;
            }, $ttl);
        });
    }

    private function buildMediaList(array $media, int $limit): array
    {
        $items = [];
        foreach (\array_slice($media, 0, $limit) as $item) {
            $items[] = [
                'id' => $item['id'] ?? null,
                'title' => $item['title']['romaji'] ?? $item['title']['english'] ?? 'Unknown',
                'year' => $item['startDate']['year'] ?? null,
                'poster' => $item['coverImage']['large'] ?? $item['coverImage']['medium'] ?? null,
                'rating' => isset($item['averageScore']) ? round((float) $item['averageScore'] / 10, 1) : null,
            ];
        }

        return $items;
    }

    private function animeQuery(string $sort, int $limit, int $ttl = 3600): array
    {
        $query = <<<'GRAPHQL'
        query ($sort: [MediaSort], $page: Int, $perPage: Int) {
            Page(page: $page, perPage: $perPage) {
                media(type: ANIME, sort: $sort) {
                    id
                    title { romaji english }
                    coverImage { large medium }
                    averageScore
                    startDate { year month day }
                }
            }
        }
        GRAPHQL;

        $media = $this->query($query, ['sort' => [$sort], 'page' => 1, 'perPage' => $limit], $ttl);

        return $media ? $this->buildMediaList($media, $limit) : [];
    }

    public function discoverAnime(string $list, int $limit = 12, int $ttl = 3600): array
    {
        $map = [
            'trending' => 'TRENDING_DESC',
            'popular' => 'POPULARITY_DESC',
            'top_rated' => 'SCORE_DESC',
            'new_releases' => 'START_DATE_DESC',
            'upcoming' => 'START_DATE_DESC',
        ];

        $sort = $map[$list] ?? null;

        if (!$sort) {
            return [];
        }

        return $this->animeQuery($sort, $limit, $ttl);
    }

    public function fetchAnime(string $anilistId): ?array
    {
        $query = <<<'GRAPHQL'
        query ($id: Int) {
            Media(id: $id, type: ANIME) {
                id
                title { romaji english }
                description
                coverImage { large medium }
                bannerImage
                averageScore
                startDate { year month day }
                endDate { year month day }
                status
                episodes
                genres
                studios { nodes { name } }
                siteUrl
            }
        }
        GRAPHQL;

        $media = $this->query($query, ['id' => (int) $anilistId], 86400);

        return $media ? $this->buildMediaList($media, 1)[0] ?? null : null;
    }

    /**
     * Title-search AniList for the first matching media of the requested type.
     *
     * Used by the metadata backfill to resolve missing external IDs for
     * anime/manga rows. Returns the raw media node (or null when not found).
     */
    public function searchMedia(string $title, string $type, ?int $year = null): ?array
    {
        $query = <<<'GRAPHQL'
        query ($search: String, $type: MediaType, $yearLike: String) {
            Page(perPage: 1) {
                media(search: $search, type: $type, startDate_like: $yearLike) {
                    id
                    title { romaji english }
                    description
                    coverImage { large medium }
                    bannerImage
                    averageScore
                    startDate { year month day }
                    endDate { year month day }
                    status
                    episodes
                    chapters
                    volumes
                    duration
                    genres
                    studios { nodes { name } }
                    siteUrl
                }
            }
        }
        GRAPHQL;

        $variables = ['search' => $title, 'type' => $type];
        if ($year > 0) {
            $variables['yearLike'] = $year.'%';
        }

        $media = $this->query($query, $variables, 86400);

        return $media[0] ?? null;
    }

    /**
     * Normalize a raw AniList media node into the same shape produced by
     * TmdbService::hydrateMetadata so the backfill command can persist it
     * uniformly regardless of the source provider.
     *
     * @return array<string, mixed>
     */
    public function hydrateMetadata(array $data): array
    {
        $title = $data['title']['romaji'] ?? $data['title']['english'] ?? null;

        $releaseDate = null;
        if (!empty($data['startDate']['year'])) {
            $releaseDate = \sprintf(
                '%04d-%02d-%02d',
                (int) $data['startDate']['year'],
                (int) ($data['startDate']['month'] ?? 1),
                (int) ($data['startDate']['day'] ?? 1),
            );
        }

        $image = $data['coverImage']['large'] ?? $data['coverImage']['medium'] ?? null;

        return [
            'mediaId' => $data['id'] ?? null,
            'externalId' => (string) ($data['id'] ?? null),

            'title' => $title,
            'overview' => $data['description'] ?? null,

            'genres' => $data['genres'] ?? [],

            'runtime' => $data['duration'] ?? null,

            'releaseDate' => $releaseDate,

            'image' => $image,
            'backdrop' => $data['bannerImage'] ?? '',

            'screenshot' => $image,
            'cast' => [],
            'trailer' => null,

            'country' => null,
        ];
    }
}
