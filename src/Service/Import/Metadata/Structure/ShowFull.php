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
 * Unified, provider-agnostic representation of a TV show.
 *
 * Every provider client (TMDB, TVDB, TVMaze) maps its native fetchFullShow()
 * response into a ShowFull so the discovery, scoring and merge engines can
 * treat all sources identically. The underlying raw provider array is kept
 * accessible via {@see ShowFull::raw()} for the hierarchy builder, which still
 * consumes the TMDB-shaped season/episode arrays.
 */
final class ShowFull
{
    public function __construct(
        public readonly string $source,        // 'tmdb' | 'tvdb' | 'tvmaze'
        public readonly ?string $title = null,
        public readonly ?string $originalTitle = null,
        public readonly ?string $overview = null,
        public readonly ?string $image = null,
        public readonly ?string $backdrop = null,
        public readonly ?int $year = null,
        public readonly ?int $runtime = null,
        public readonly array $genres = [],
        public readonly ?string $country = null,
        public readonly ?string $network = null,
        public readonly ?string $status = null,
        public readonly ExternalIds $externalIds = new ExternalIds(),
        /** @var array<int, SeasonFull> */
        public readonly array $seasons = [],
        /** @var array<int, CastFull> */
        public readonly array $cast = [],
        public readonly ?string $trailer = null,
        public readonly ?float $rating = null,
        public readonly ?int $ratingCount = null,
        private readonly ?array $raw = null,
    ) {
    }

    /**
     * The raw provider array (TMDB-shaped), used by the hierarchy builder.
     *
     * @return array<string, mixed>|null
     */
    public function raw(): ?array
    {
        return $this->raw;
    }

    public function episodeCount(): int
    {
        $total = 0;
        foreach ($this->seasons as $season) {
            $total += \count($season->episodes);
        }

        return $total;
    }

    public function seasonCount(): int
    {
        return \count($this->seasons);
    }
}
