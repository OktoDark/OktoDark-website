<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Metadata;

class MetadataHydrator
{
    private const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/w500';

    public function hydrateTmdbMovie(array $tmdb): array
    {
        $poster = $tmdb['poster_path'] ?? null;

        return [
            'externalId' => (string) $tmdb['id'],
            'title' => $tmdb['title'] ?? null,
            'overview' => $tmdb['overview'] ?? null,
            'image' => $poster ? self::TMDB_IMAGE_BASE.$poster : null,
            'runtime' => $tmdb['runtime'] ?? null,
            'genres' => $tmdb['genres'] ?? [],
            'releaseDate' => $tmdb['release_date'] ?? null,
        ];
    }

    public function hydrateTmdbShow(array $tmdb): array
    {
        $poster = $tmdb['poster_path'] ?? null;

        return [
            'externalId' => (string) $tmdb['id'],
            'title' => $tmdb['name'] ?? null,
            'overview' => $tmdb['overview'] ?? null,
            'image' => $poster ? self::TMDB_IMAGE_BASE.$poster : null,
            'runtime' => $tmdb['episode_run_time'][0] ?? null,
            'genres' => $tmdb['genres'] ?? [],
            'releaseDate' => $tmdb['first_air_date'] ?? null,
            'country' => isset($tmdb['origin_country']) ? implode(', ', $tmdb['origin_country']) : null,
        ];
    }

    public function hydrateTmdbSeason(array $season): array
    {
        $poster = $season['poster_path'] ?? null;

        return [
            'id' => $season['id'] ?? null,
            'externalId' => (string) ($season['id'] ?? ''),
            'title' => $season['name'] ?? null,
            'overview' => $season['overview'] ?? null,
            'image' => $poster ? self::TMDB_IMAGE_BASE.$poster : null,
            'releaseDate' => $season['air_date'] ?? null,
            'seasonNumber' => $season['season_number'] ?? null,
        ];
    }

    public function hydrateTmdbEpisode(array $ep): array
    {
        $still = $ep['still_path'] ?? null;
        $image = $still ? self::TMDB_IMAGE_BASE.$still : null;

        return [
            'id' => $ep['id'] ?? null,
            'externalId' => (string) ($ep['id'] ?? ''),
            'title' => $ep['name'] ?? null,
            'overview' => $ep['overview'] ?? null,
            'image' => $image,
            'screenshot' => $image,
            'cast' => [],
            'trailer' => null,
            'releaseDate' => $ep['air_date'] ?? null,
            'seasonNumber' => $ep['season_number'] ?? null,
            'episodeNumber' => $ep['episode_number'] ?? null,
        ];
    }
}
