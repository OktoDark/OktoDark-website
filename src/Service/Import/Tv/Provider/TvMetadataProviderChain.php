<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv\Provider;

use App\Entity\MediaMetadata;

/**
 * Tries each configured TV metadata provider in priority order until one
 * resolves the requested data. The canonical order is:
 *
 *   TMDB   → identity, images, cast, translations
 *   TVDB   → episodes, air dates, numbering
 *   TVMaze → status, schedule, next episode
 *
 * Unconfigured providers (e.g. TVDB without TVDB_API_KEY) are skipped
 * automatically so the chain degrades gracefully.
 */
class TvMetadataProviderChain implements TvMetadataProviderInterface
{
    /** @var array<int, TvMetadataProviderInterface> */
    private array $providers;

    /**
     * @param iterable<TvMetadataProviderInterface> $providers Ordered by preference
     */
    public function __construct(iterable $providers)
    {
        $this->providers = \is_array($providers) ? $providers : iterator_to_array($providers);
    }

    public function getSource(): \App\Enum\Source
    {
        return \App\Enum\Source::MANUAL;
    }

    public function isConfigured(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a show id using the first configured provider that returns one.
     */
    public function resolveShowId(string $title): ?string
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isConfigured()) {
                continue;
            }

            try {
                $id = $provider->resolveShowId($title);
            } catch (\Throwable) {
                continue;
            }

            if (null !== $id) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Enrich a TV-level record with the first provider able to resolve it.
     */
    public function enrichShow(MediaMetadata $meta, array $ids = [], bool $force = true): void
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isConfigured()) {
                continue;
            }

            $before = $this->snapshot($meta);
            try {
                $provider->enrichShow($meta, $ids, $force);
            } catch (\Throwable) {
                continue;
            }

            if ($this->changed($meta, $before)) {
                $meta->setSource($provider->getSource());
                return;
            }
        }
    }

    public function enrichSeason(MediaMetadata $showMeta, MediaMetadata $seasonMeta, array $ids = [], bool $force = true): void
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isConfigured()) {
                continue;
            }

            try {
                $provider->enrichSeason($showMeta, $seasonMeta, $ids, $force);
            } catch (\Throwable) {
                continue;
            }

            if ($seasonMeta->getImage() || $seasonMeta->getOverview()) {
                $seasonMeta->setSource($provider->getSource());
                return;
            }
        }
    }

    public function enrichEpisode(MediaMetadata $showMeta, MediaMetadata $epMeta, array $ids = [], bool $force = true): void
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isConfigured()) {
                continue;
            }

            try {
                $provider->enrichEpisode($showMeta, $epMeta, $ids, $force);
            } catch (\Throwable) {
                continue;
            }

            if ($epMeta->getImage() || $epMeta->getOverview()) {
                $epMeta->setSource($provider->getSource());
                return;
            }
        }
    }

    /**
     * Fetch season episodes from the first provider that returns any.
     */
    public function getSeasonEpisodes(string $showId, int $seasonNumber): array
    {
        foreach ($this->providers as $provider) {
            if (!$provider->isConfigured()) {
                continue;
            }

            try {
                $episodes = $provider->getSeasonEpisodes($showId, $seasonNumber);
            } catch (\Throwable) {
                continue;
            }

            if ([] !== $episodes) {
                return $episodes;
            }
        }

        return [];
    }

    /**
     * @return array{image:?string, overview:?string, externalId:?string, tmdbId:?string}
     */
    private function snapshot(MediaMetadata $meta): array
    {
        return [
            'image' => $meta->getImage(),
            'overview' => $meta->getOverview(),
            'externalId' => $meta->getExternalId(),
            'tmdbId' => $meta->getTmdbId(),
        ];
    }

    /**
     * @param array{image:?string, overview:?string, externalId:?string, tmdbId:?string} $before
     */
    private function changed(MediaMetadata $meta, array $before): bool
    {
        return $meta->getImage() !== $before['image']
            || $meta->getOverview() !== $before['overview']
            || $meta->getExternalId() !== $before['externalId']
            || $meta->getTmdbId() !== $before['tmdbId'];
    }
}
