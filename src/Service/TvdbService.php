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

/**
 * TheTVDB v4 API client.
 *
 * Acts as a secondary metadata provider used as a fallback when TMDB has no
 * match for a show/episode. Unlike TMDB, TheTVDB v4 uses bearer-token auth:
 * we exchange the API key for a JWT via POST /login and cache it, then send it
 * as an Authorization header on every request.
 *
 * The public fetch* methods intentionally return the SAME array shape as
 * {@see TmdbService} so this service can be dropped into the existing
 * MetadataMergeService / TvHierarchyBuilder pipeline without further mapping.
 */
class TvdbService
{
    private const BASE = 'https://api4.thetvdb.com/v4';

    // TheTVDB tokens are valid for ~1 month; refresh comfortably before that.
    private const TOKEN_TTL = 20 * 24 * 3600;

    private string $apiKey;
    private HttpClientInterface $client;
    private CacheInterface $cache;

    public function __construct(
        HttpClientInterface $client,
        CacheInterface $cache,
        string $tvdbApiKey,
    ) {
        $this->client = $client;
        $this->cache = $cache;
        $this->apiKey = $tvdbApiKey;
    }

    /**
     * Whether the service has an API key configured.
     */
    public function isConfigured(): bool
    {
        return '' !== mb_trim($this->apiKey);
    }

    // ---------------------------------------------------------
    // AUTH
    // ---------------------------------------------------------
    private function token(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            return $this->cache->get('tvdb_token_'.md5($this->apiKey), function (ItemInterface $item): ?string {
                $item->expiresAfter(self::TOKEN_TTL);

                $response = $this->client->request('POST', self::BASE.'/login', [
                    'json' => ['apikey' => $this->apiKey],
                ]);

                if (200 !== $response->getStatusCode()) {
                    return null;
                }

                $token = $response->toArray(false)['data']['token'] ?? null;

                return \is_string($token) ? $token : null;
            });
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------------------------------------------------------
    // LOW-LEVEL GET (cached)
    // ---------------------------------------------------------
    private function safeGet(string $path, array $params = [], int $ttl = 86400): ?array
    {
        try {
            return $this->get($path, $params, $ttl);
        } catch (\Throwable) {
            return null;
        }
    }

    private function get(string $path, array $params = [], int $ttl = 86400): ?array
    {
        $token = $this->token();
        if (null === $token) {
            return null;
        }

        $cacheKey = 'tvdb_'.md5($path.json_encode($params));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($path, $params, $token, $ttl) {
            $item->expiresAfter($ttl);

            try {
                $response = $this->client->request('GET', self::BASE.$path, [
                    'query' => $params,
                    'headers' => [
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/json',
                    ],
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
    // SEARCH
    // ---------------------------------------------------------
    /**
     * Resolve a TheTVDB series id from a known id or a title.
     */
    public function findShowId(array $ids, ?string $title = null, ?int $year = null): ?int
    {
        if (!empty($ids['tvdb'])) {
            return (int) $ids['tvdb'];
        }

        if ($title) {
            $params = ['query' => $title, 'type' => 'series'];
            if ($year) {
                $params['year'] = $year;
            }

            $data = $this->safeGet('/search', $params);
            $first = $data['data'][0] ?? null;

            // TheTVDB search ids look like "series-12345"; prefer tvdb_id when present.
            if (isset($first['tvdb_id'])) {
                return (int) $first['tvdb_id'];
            }
            if (isset($first['id']) && preg_match('/(\d+)/', (string) $first['id'], $m)) {
                return (int) $m[1];
            }
        }

        return null;
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

        $params = ['query' => $title, 'type' => 'series'];
        if ($year) {
            $params['year'] = $year;
        }

        $data = $this->safeGet('/search', $params);

        return $data['data'] ?? [];
    }

    /**
     * Extract the normalized external id set from a raw TVDB show record.
     *
     * @param array<string, mixed> $show
     */
    public function extractExternalIds(array $show): Import\Metadata\Structure\ExternalIds
    {
        $remoteIds = $show['remoteIds'] ?? [];

        return new Import\Metadata\Structure\ExternalIds(
            tvdb: isset($show['id']) ? (string) $show['id'] : null,
        );
    }

    // SHOW
    // ---------------------------------------------------------
    /**
     * Raw extended series record (includes seasons, artwork, genres).
     */
    public function getShow(int $tvdbId): ?array
    {
        $data = $this->safeGet('/series/'.$tvdbId.'/extended');

        return $data['data'] ?? null;
    }

    // ---------------------------------------------------------
    // EPISODES
    // ---------------------------------------------------------
    /**
     * All episodes for a series in the "default" (aired) order.
     *
     * @return array<int, array<string, mixed>> raw TheTVDB episode records
     */
    public function getEpisodes(int $tvdbId, string $seasonType = 'default'): array
    {
        $episodes = [];
        $page = 0;

        // TheTVDB paginates episodes; walk pages until exhausted (hard cap for safety).
        do {
            $data = $this->safeGet('/series/'.$tvdbId.'/episodes/'.$seasonType, ['page' => $page]);
            $chunk = $data['data']['episodes'] ?? [];

            foreach ($chunk as $ep) {
                $episodes[] = $ep;
            }

            $hasNext = !empty($data['links']['next']);
            ++$page;
        } while ($hasNext && $page < 50);

        return $episodes;
    }

    // ---------------------------------------------------------
    // FULL SHOW (SHOW + SEASONS + EPISODES) — TMDB-compatible shape
    // ---------------------------------------------------------
    /**
     * Returns a structure matching {@see TmdbService::fetchFullShow()} so it can be
     * consumed as a drop-in TMDB fallback:.
     *
     *   [
     *     'show'    => [ id, name, overview, image, genres, first_air_date, episode_run_time ],
     *     'seasons' => [ <seasonNumber> => [ 'season' => [...], 'episodes' => [...] ] ],
     *   ]
     */
    public function fetchFullShow(int $tvdbId): ?array
    {
        $raw = $this->getShow($tvdbId);
        if (!$raw) {
            return null;
        }

        $show = $this->hydrateShow($raw);

        $episodes = $this->getEpisodes($tvdbId);

        $seasons = [];
        foreach ($episodes as $ep) {
            $seasonNumber = $ep['seasonNumber'] ?? null;
            if (null === $seasonNumber) {
                continue;
            }
            $seasonNumber = (int) $seasonNumber;

            if (!isset($seasons[$seasonNumber])) {
                $seasons[$seasonNumber] = [
                    'season' => [
                        'id' => null,
                        'season_number' => $seasonNumber,
                        'name' => 'Season '.$seasonNumber,
                        'overview' => null,
                        'air_date' => null,
                        'poster_path' => null,
                        'image' => $show['image'] ?? '',
                    ],
                    'episodes' => [],
                ];
            }

            $seasons[$seasonNumber]['episodes'][] = $this->hydrateEpisode($ep);
        }

        ksort($seasons);

        return [
            'show' => $show,
            'seasons' => $seasons,
        ];
    }

    // ---------------------------------------------------------
    // HYDRATION (map TheTVDB fields to the TMDB-like contract)
    // ---------------------------------------------------------
    private function hydrateShow(array $raw): array
    {
        $genres = [];
        foreach ($raw['genres'] ?? [] as $g) {
            if (isset($g['name'])) {
                $genres[] = ['name' => $g['name']];
            }
        }

        $runtime = $raw['averageRuntime'] ?? null;

        return [
            'id' => $raw['id'] ?? null,
            'name' => $raw['name'] ?? null,
            'overview' => $raw['overview'] ?? null,
            'image' => $raw['image'] ?? '',
            'genres' => $genres,
            'first_air_date' => $raw['firstAired'] ?? null,
            'episode_run_time' => null !== $runtime ? [(int) $runtime] : [],
            'origin_country' => isset($raw['originalCountry']) ? [$raw['originalCountry']] : [],
        ];
    }

    private function hydrateEpisode(array $ep): array
    {
        return [
            'id' => $ep['id'] ?? null,
            'name' => $ep['name'] ?? null,
            'overview' => $ep['overview'] ?? null,
            'air_date' => $ep['aired'] ?? null,
            'runtime' => $ep['runtime'] ?? null,
            'still_path' => null, // TheTVDB gives absolute URLs, not TMDB-style paths
            'image' => $ep['image'] ?? '',
            'season_number' => isset($ep['seasonNumber']) ? (int) $ep['seasonNumber'] : null,
            'episode_number' => isset($ep['number']) ? (int) $ep['number'] : null,
        ];
    }
}
