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
use App\Enum\Source;

/**
 * Unified metadata provider contract for TV show imports.
 *
 * Each concrete provider (TVMaze, TheTVDB, TMDB) maps its native API response
 * into the same normalized shapes so the TvTimeCsvImporter can consume any of
 * them interchangeably and fall back from one to the next without special casing.
 */
interface TvMetadataProviderInterface
{
    /**
     * The source backing this provider (used to tag metadata records).
     */
    public function getSource(): Source;

    /**
     * Whether this provider is ready to serve requests (API key configured, etc.).
     */
    public function isConfigured(): bool;

    /**
     * Resolve a native show id from a title. Returns null when no match.
     */
    public function resolveShowId(string $title): ?string;

    /**
     * Enrich a TV-level MediaMetadata record with poster/overview/genres/etc.
     *
     * @param array<string, string> $ids External ids already known (tvmaze, tvdb, tmdb)
     */
    public function enrichShow(MediaMetadata $meta, array $ids = [], bool $force = true): void;

    /**
     * Enrich a Season-level MediaMetadata record.
     */
    public function enrichSeason(MediaMetadata $showMeta, MediaMetadata $seasonMeta, array $ids = [], bool $force = true): void;

    /**
     * Enrich an Episode-level MediaMetadata record.
     */
    public function enrichEpisode(MediaMetadata $showMeta, MediaMetadata $epMeta, array $ids = [], bool $force = true): void;

    /**
     * Fetch all episodes for a given season (aired order).
     *
     * @return array<int, array{season:int, number:int, title?:?string, airdate?:?string, image?:?string, overview?:?string, runtime?:?int}>
     */
    public function getSeasonEpisodes(string $showId, int $seasonNumber): array;
}
