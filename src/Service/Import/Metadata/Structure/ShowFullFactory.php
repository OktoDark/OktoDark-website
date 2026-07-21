<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Metadata\Structure;

/**
 * Maps a provider's raw fetchFullShow() response (TMDB-shaped) into a ShowFull.
 *
 * TMDB, TVDB and TVMaze clients already normalize their responses to the same
 * TMDB-compatible shape, so a single factory handles all three sources. The
 * raw array is preserved on the ShowFull for the hierarchy builder.
 */
final class ShowFullFactory
{
    /**
     * @param array<string, mixed>|null $full Raw fetchFullShow() response
     */
    public static function fromRaw(?array $full, string $source): ?ShowFull
    {
        if (null === $full || !isset($full['show'])) {
            return null;
        }

        $show = $full['show'];

        $genres = [];
        foreach ($show['genres'] ?? [] as $g) {
            if (\is_array($g)) {
                $genres[] = $g['name'] ?? null;
            } elseif (\is_string($g)) {
                $genres[] = $g;
            }
        }
        $genres = array_values(array_filter($genres));

        $externalIds = new ExternalIds(
            tmdb: isset($show['id']) && 'tmdb' === $source ? (string) $show['id'] : null,
            tvdb: isset($show['id']) && 'tvdb' === $source ? (string) $show['id'] : null,
            tvmaze: isset($show['id']) && 'tvmaze' === $source ? (string) $show['id'] : null,
        );

        $year = null;
        if (!empty($show['first_air_date'])) {
            $year = (int) mb_substr((string) $show['first_air_date'], 0, 4);
        } elseif (!empty($show['releaseDate'])) {
            $year = (int) mb_substr((string) $show['releaseDate'], 0, 4);
        }

        $seasons = [];
        foreach ($full['seasons'] ?? [] as $seasonNumber => $seasonData) {
            $season = $seasonData['season'] ?? $seasonData['metadata'] ?? [];
            $episodes = $seasonData['episodes'] ?? [];

            $seasons[(int) $seasonNumber] = SeasonFull::fromArray($season, $episodes);
        }
        ksort($seasons);

        return new ShowFull(
            source: $source,
            title: $show['name'] ?? $show['title'] ?? null,
            originalTitle: $show['original_name'] ?? $show['original_title'] ?? null,
            overview: $show['overview'] ?? null,
            image: $show['image'] ?? null,
            year: $year,
            runtime: isset($show['episode_run_time'][0]) ? (int) $show['episode_run_time'][0] : ($show['runtime'] ?? null),
            genres: $genres,
            country: $show['origin_country'][0] ?? $show['country'] ?? null,
            externalIds: $externalIds,
            seasons: $seasons,
            raw: $full,
        );
    }

    /**
     * Map a raw TMDB movie record (the hydrated shape returned by
     * {@see \App\Service\TmdbService::findMovie()}) into a ShowFull. Movies carry
     * no seasons/episodes, so the structure is flattened onto the show itself and
     * the raw movie array is preserved for the merge engine.
     *
     * @param array<string, mixed> $movie Raw TMDB movie record
     */
    public static function fromMovieRaw(?array $movie, string $source = 'tmdb'): ?ShowFull
    {
        if (null === $movie) {
            return null;
        }

        $genres = [];
        foreach ($movie['genres'] ?? [] as $g) {
            if (\is_array($g)) {
                $genres[] = $g['name'] ?? null;
            } elseif (\is_string($g)) {
                $genres[] = $g;
            }
        }
        $genres = array_values(array_filter($genres));

        $year = null;
        if (!empty($movie['release_date'])) {
            $year = (int) mb_substr((string) $movie['release_date'], 0, 4);
        }

        $externalIds = new ExternalIds(
            tmdb: isset($movie['id']) ? (string) $movie['id'] : null,
        );

        return new ShowFull(
            source: $source,
            title: $movie['title'] ?? null,
            originalTitle: $movie['original_title'] ?? null,
            overview: $movie['overview'] ?? null,
            image: $movie['image'] ?? null,
            year: $year,
            runtime: isset($movie['runtime']) ? (int) $movie['runtime'] : null,
            genres: $genres,
            externalIds: $externalIds,
            rating: isset($movie['vote_average']) ? (float) $movie['vote_average'] : null,
            ratingCount: isset($movie['vote_count']) ? (int) $movie['vote_count'] : null,
            raw: $movie,
        );
    }
}
