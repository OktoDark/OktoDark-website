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

class GameGridItem
{
    public int $id;
    public string $title;
    public string $coverUrl;

    public ?string $releaseDate;
    public ?int $runtime;

    public string $statusLabel;
    public string $statusClass;

    public ?int $score;
    public ?string $notes;

    public function __construct(
        int     $id,
        string  $title,
        ?string $coverUrl,
        ?string $releaseDate,
        ?int    $runtime,
        string  $statusLabel,
        string  $statusClass,
        ?int    $score,
        ?string $notes
    )
    {
        $this->id = $id;
        $this->title = $title;
        $this->coverUrl = $coverUrl ?? '';

        $this->releaseDate = $releaseDate;
        $this->runtime = $runtime;

        $this->statusLabel = $statusLabel;
        $this->statusClass = $statusClass;

        $this->score = $score;
        $this->notes = $notes;
    }

    public function getFormattedRuntime(): string
    {
        return $this->runtime ? $this->runtime . ' hrs' : '—';
    }

    public function getFormattedReleaseYear(): string
    {
        return $this->releaseDate ?: '—';
    }
}


