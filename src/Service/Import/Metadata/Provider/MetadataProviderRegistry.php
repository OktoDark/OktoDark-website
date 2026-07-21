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

use App\Service\Import\Metadata\Structure\ShowFull;

/**
 * Aggregates every registered {@see MetadataProviderInterface}.
 *
 * The discovery engine asks the registry for all providers that support a given
 * media type, then fetches/merges from each. Adding a new source (AniList, MAL,
 * JustWatch, RottenTomatoes, Letterboxd) requires no change here — only a new
 * service tagged `app.metadata_provider`.
 */
class MetadataProviderRegistry
{
    /** @var array<string, MetadataProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<MetadataProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getName()] = $provider;
        }
    }

    /**
     * @return array<string, MetadataProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Configured providers that can resolve the requested media type, in
     * registration order (source priority: TMDB first, then others).
     *
     * @return array<string, MetadataProviderInterface>
     */
    public function supports(string $mediaType): array
    {
        $out = [];
        foreach ($this->providers as $name => $provider) {
            if ($provider->isConfigured() && $provider->supports($mediaType)) {
                $out[$name] = $provider;
            }
        }

        return $out;
    }

    /**
     * Fetch a normalized record from the first configured provider able to
     * resolve the given ids.
     */
    public function fetchByIds(array $ids, string $mediaType): ?ShowFull
    {
        foreach ($this->supports($mediaType) as $provider) {
            try {
                $show = $provider->fetchByIds($ids, $mediaType);
            } catch (\Throwable) {
                continue;
            }

            if ($show) {
                return $show;
            }
        }

        return null;
    }

    /**
     * Search a normalized record from the first configured provider able to
     * match the title + year.
     */
    public function searchByTitle(string $title, ?int $year, string $mediaType): ?ShowFull
    {
        foreach ($this->supports($mediaType) as $provider) {
            try {
                $show = $provider->searchByTitle($title, $year, $mediaType);
            } catch (\Throwable) {
                continue;
            }

            if ($show) {
                return $show;
            }
        }

        return null;
    }
}
