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
}
