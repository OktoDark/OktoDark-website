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
 * Normalized episode record (aired order, provider-agnostic).
 */
final class EpisodeFull
{
    public function __construct(
        public readonly ?int $seasonNumber = null,
        public readonly ?int $episodeNumber = null,
        public readonly ?string $title = null,
        public readonly ?string $overview = null,
        public readonly ?string $airDate = null,
        public readonly ?int $runtime = null,
        public readonly ?string $image = null,
        public readonly ?string $still = null,
        public readonly array $cast = [],
        public readonly ?string $trailer = null,
        public readonly ?string $externalId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data Raw episode record (TMDB/TVDB/TVMaze shape)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            seasonNumber: isset($data['season_number']) ? (int) $data['season_number'] : (isset($data['season']) ? (int) $data['season'] : null),
            episodeNumber: isset($data['episode_number']) ? (int) $data['episode_number'] : (isset($data['number']) ? (int) $data['number'] : null),
            title: $data['name'] ?? $data['title'] ?? null,
            overview: $data['overview'] ?? $data['summary'] ?? null,
            airDate: $data['air_date'] ?? $data['aired'] ?? $data['airdate'] ?? null,
            runtime: isset($data['runtime']) ? (int) $data['runtime'] : null,
            image: $data['image'] ?? null,
            still: $data['still'] ?? $data['screenshot'] ?? null,
            cast: $data['cast'] ?? [],
            trailer: $data['trailer'] ?? null,
            externalId: isset($data['id']) ? (string) $data['id'] : null,
        );
    }
}
