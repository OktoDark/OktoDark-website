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
 * Normalized season record (provider-agnostic).
 */
final class SeasonFull
{
    public function __construct(
        public readonly ?int $seasonNumber = null,
        public readonly ?string $title = null,
        public readonly ?string $overview = null,
        public readonly ?string $airDate = null,
        public readonly ?string $image = null,
        public readonly ?string $externalId = null,
        /** @var array<int, EpisodeFull> */
        public readonly array $episodes = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data Raw season record (TMDB/TVDB/TVMaze shape)
     */
    public static function fromArray(array $data, array $episodes = []): self
    {
        return new self(
            seasonNumber: isset($data['season_number']) ? (int) $data['season_number'] : (isset($data['number']) ? (int) $data['number'] : null),
            title: $data['name'] ?? $data['title'] ?? null,
            overview: $data['overview'] ?? $data['summary'] ?? null,
            airDate: $data['air_date'] ?? $data['premiereDate'] ?? $data['aired'] ?? null,
            image: $data['image'] ?? null,
            externalId: isset($data['id']) ? (string) $data['id'] : null,
            episodes: array_map([EpisodeFull::class, 'fromArray'], $episodes),
        );
    }
}
