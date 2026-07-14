<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Dto;

class AnimeGridItem
{
    public int $id;
    public string $title;
    public string $coverUrl;

    public ?string $releaseDate;
    public ?int $runtime;

    public string $statusLabel;
    public string $statusClass;

    public int $watchedEpisodes;
    public int $totalEpisodes;

    /** @var SeasonGridItem[] */
    public array $seasons = [];

    public ?int $score;
    public ?string $notes;

    public function __construct(
        int $id,
        string $title,
        ?string $coverUrl,
        ?string $releaseDate,
        ?int $runtime,
        string $statusLabel,
        string $statusClass,
        int $watchedEpisodes,
        int $totalEpisodes,
        array $seasons,
        ?int $score,
        ?string $notes,
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->coverUrl = $coverUrl ?? '';

        $this->releaseDate = $releaseDate;
        $this->runtime = $runtime;

        $this->statusLabel = $statusLabel;
        $this->statusClass = $statusClass;

        $this->watchedEpisodes = $watchedEpisodes;
        $this->totalEpisodes = $totalEpisodes;

        $this->seasons = $seasons;

        $this->score = $score;
        $this->notes = $notes;
    }

    public function getFormattedEpisodeCount(): string
    {
        return \sprintf('%d / %d', $this->watchedEpisodes, $this->totalEpisodes);
    }

    public function getFormattedReleaseYear(): string
    {
        return $this->releaseDate ?: '—';
    }
}
