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

class TvShowGridItem
{
    public int $id;
    public string $title;
    public string $coverUrl;

    public string $statusLabel;
    public string $statusClass;

    public ?string $releaseDate;

    public int $watchedEpisodes;
    public int $totalEpisodes;

    /** @var SeasonGridItem[] */
    public array $seasons = [];

    public function __construct(
        int     $id,
        string  $title,
        ?string $coverUrl,
        string  $statusLabel,
        string  $statusClass,
        ?string $releaseDate,
        int     $watchedEpisodes,
        int     $totalEpisodes,
        array   $seasons
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->coverUrl = $coverUrl ?? '';

        $this->statusLabel = $statusLabel;
        $this->statusClass = $statusClass;

        $this->releaseDate = $releaseDate;

        $this->watchedEpisodes = $watchedEpisodes;
        $this->totalEpisodes = $totalEpisodes;

        $this->seasons = $seasons;
    }

    /**
     * Returns "watched / total".
     */
    public function getFormattedEpisodeCount(): string
    {
        return sprintf('%d / %d', $this->watchedEpisodes, $this->totalEpisodes);
    }

    /**
     * Returns release year or "—".
     */
    public function getFormattedReleaseYear(): string
    {
        return $this->releaseDate ?: '—';
    }

    /**
     * Returns true if the show has no watched episodes.
     */
    public function isReadyToStart(): bool
    {
        return $this->watchedEpisodes === 0;
    }

    /**
     * Returns true if the show is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->watchedEpisodes > 0 && $this->watchedEpisodes < $this->totalEpisodes;
    }

    /**
     * Returns true if the show is fully completed.
     */
    public function isCompleted(): bool
    {
        return $this->totalEpisodes > 0 && $this->watchedEpisodes >= $this->totalEpisodes;
    }
}
