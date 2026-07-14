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

class RecentlyWatchedItem
{
    public int $tvId;
    public string $showTitle;
    public string $episodeTitle;
    public string $formattedEpisode;
    public ?string $coverUrl;
    public \DateTimeInterface $watchedAt;

    public function __construct(
        int $tvId,
        string $showTitle,
        string $episodeTitle,
        string $formattedEpisode,
        ?string $coverUrl,
        \DateTimeInterface $watchedAt,
    ) {
        $this->tvId = $tvId;
        $this->showTitle = $showTitle;
        $this->episodeTitle = $episodeTitle;
        $this->formattedEpisode = $formattedEpisode ?: '—';
        $this->coverUrl = $coverUrl;
        $this->watchedAt = $watchedAt;
    }

    /**
     * Returns the watched date in Y-m-d format.
     */
    public function getFormattedDate(): string
    {
        return $this->watchedAt->format('Y-m-d');
    }

    /**
     * Returns a short relative time (e.g., "3 days ago").
     */
    public function getRelativeTime(): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->watchedAt);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }

        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }

        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }

        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }

        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }

        return 'just now';
    }

    /**
     * Returns only the episode number (e.g., "E03").
     */
    public function getShortEpisode(): string
    {
        if (!preg_match('/E(\d+)/', $this->formattedEpisode, $m)) {
            return '—';
        }

        return 'E' . $m[1];
    }
}
