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

class NextEpisodeItem
{
    public int $tvId;
    public string $showTitle;
    public ?string $coverUrl;
    public ?int $season;
    public ?int $episode;
    public ?\DateTimeInterface $airDate;

    public function __construct(
        int $tvId,
        string $showTitle,
        ?string $coverUrl,
        ?int $season,
        ?int $episode,
        ?\DateTimeInterface $airDate,
    ) {
        $this->tvId = $tvId;
        $this->showTitle = $showTitle;
        $this->coverUrl = $coverUrl;
        $this->season = $season;
        $this->episode = $episode;
        $this->airDate = $airDate;
    }

    public function getFormattedEpisode(): string
    {
        return sprintf(
            'S%02dE%02d',
            $this->season ?? 0,
            $this->episode ?? 0
        );
    }
}
