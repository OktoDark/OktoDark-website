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

class SeasonGridItem
{
    public int $seasonNumber;
    public string $title;
    public string $coverUrl;
    public ?string $releaseDate;

    public string $statusLabel;
    public string $statusClass;

    public int $watchedEpisodes;
    public int $totalEpisodes;
    public int $progress;

    public ?int $score;
    public ?string $notes;

    public function __construct(
        int $seasonNumber,
        ?string $title,
        ?string $coverUrl,
        ?string $releaseDate,
        string $statusLabel,
        string $statusClass,
        int $watchedEpisodes,
        int $totalEpisodes,
        int $progress,
        ?int $score,
        ?string $notes
    ) {
        $this->seasonNumber = $seasonNumber;
        $this->title = $title ?? '';
        $this->coverUrl = $coverUrl ?? '';
        $this->releaseDate = $releaseDate;

        $this->statusLabel = $statusLabel;
        $this->statusClass = $statusClass;

        $this->watchedEpisodes = $watchedEpisodes;
        $this->totalEpisodes = $totalEpisodes;
        $this->progress = $progress;

        $this->score = $score;
        $this->notes = $notes;
    }

    /**
     * Returns "Season X".
     */
    public function getFormattedSeason(): string
    {
        return sprintf('Season %d', $this->seasonNumber);
    }

    /**
     * Returns "watched / total".
     */
    public function getFormattedEpisodeCount(): string
    {
        return sprintf('%d / %d', $this->watchedEpisodes, $this->totalEpisodes);
    }
}
