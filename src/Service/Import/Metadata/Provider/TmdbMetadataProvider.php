<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Metadata\Provider;

use App\Enum\MediaType;
use App\Service\Import\Metadata\Structure\ShowFull;
use App\Service\Import\Metadata\Structure\ShowFullFactory;
use App\Service\TmdbService;

/**
 * Reference metadata provider backed by TMDB.
 *
 * Implements {@see MetadataProviderInterface} so the discovery engine can treat
 * TMDB like any other future source (AniList, MAL, …). New providers follow the
 * exact same shape: map their native response into a ShowFull and register the
 * service with the `app.metadata_provider` tag.
 */
class TmdbMetadataProvider implements MetadataProviderInterface
{
    public function __construct(private TmdbService $tmdb)
    {
    }

    public function getName(): string
    {
        return 'tmdb';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supports(string $mediaType): bool
    {
        return MediaType::MOVIE === $mediaType || MediaType::TV === $mediaType;
    }

    public function fetchByIds(array $ids, string $mediaType): ?ShowFull
    {
        if (MediaType::MOVIE === $mediaType) {
            if (empty($ids['tmdb'])) {
                return null;
            }

            $raw = $this->tmdb->findMovie(['tmdb' => $ids['tmdb']], null, null);

            return ShowFullFactory::fromMovieRaw($raw);
        }

        if (empty($ids['tmdb'])) {
            return null;
        }

        $full = $this->tmdb->fetchFullShow((int) $ids['tmdb']);

        return ShowFullFactory::fromRaw($full, 'tmdb');
    }

    public function searchByTitle(string $title, ?int $year, string $mediaType): ?ShowFull
    {
        if (MediaType::MOVIE === $mediaType) {
            $raw = $this->tmdb->findMovie([], $title, $year);

            return ShowFullFactory::fromMovieRaw($raw);
        }

        foreach ($this->tmdb->searchShowList($title, $year) as $hit) {
            $full = ShowFullFactory::fromRaw($this->tmdb->fetchFullShow((int) ($hit['id'] ?? 0)), 'tmdb');
            if ($full) {
                return $full;
            }
        }

        return null;
    }
}
