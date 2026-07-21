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
 * Media-agnostic metadata provider contract (roadmap Phase 9).
 *
 * A "metadata provider" enriches a title/year/ids request into a normalized
 * {@see ShowFull}. This is deliberately decoupled from the input *record*
 * providers (Trakt / TVTime / Letterboxd / …) so that new sources — AniList,
 * MyAnimeList, JustWatch, RottenTomatoes, Letterboxd — can be added by simply
 * implementing this interface and tagging the service, without touching the TV
 * or Movie importers.
 */
interface MetadataProviderInterface
{
    /**
     * Stable machine name of the provider (tmdb, tvdb, tvmaze, anilist, …).
     */
    public function getName(): string;

    /**
     * Whether this provider is ready to serve requests (API key configured, etc.).
     */
    public function isConfigured(): bool;

    /**
     * Whether this provider can resolve the given media type.
     */
    public function supports(string $mediaType): bool;

    /**
     * Resolve a full metadata record by a known external id.
     *
     * @param array{tmdb?:string|int, tvdb?:string|int, tvmaze?:string|int} $ids
     */
    public function fetchByIds(array $ids, string $mediaType): ?ShowFull;

    /**
     * Resolve a full metadata record by title + optional year.
     */
    public function searchByTitle(string $title, ?int $year, string $mediaType): ?ShowFull;
}
