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

class ContinueWatchingItem
{
    public int $tvId;
    public string $title;
    public string $coverUrl;

    /**
     * Next episode:
     * [
     *     'season' => int,
     *     'number' => int
     * ]
     */
    public ?array $nextEpisode = null;

    public string $status; // completed / in_progress / new
    public int $progressPercent = 0;

    /**
     * Most recent watched episode timestamp (or creation date as fallback),
     * used to globally sort the merged TV + Anime "Continue Watching" list
     * by recent watching activity.
     */
    public ?\DateTimeInterface $recentWatchedAt = null;

    public function __construct(
        int $tvId,
        string $title,
        ?string $coverUrl,
        ?int $nextSeason,
        ?int $nextEpisode,
        bool $isCompleted,
        bool $isInProgress,
        int $progressPercent,
        ?\DateTimeInterface $recentWatchedAt = null,
    ) {
        $this->tvId = $tvId;
        $this->title = $title;
        $this->coverUrl = $coverUrl ?? '';

        // Convert season + episode into the array Twig expects
        if (null !== $nextSeason && null !== $nextEpisode) {
            $this->nextEpisode = [
                'season' => $nextSeason,
                'number' => $nextEpisode,
            ];
        }

        // Convert booleans into a string status Twig expects
        if ($isCompleted) {
            $this->status = 'completed';
        } elseif ($isInProgress) {
            $this->status = 'in_progress';
        } else {
            $this->status = 'new';
        }

        $this->progressPercent = $progressPercent;
        $this->recentWatchedAt = $recentWatchedAt;
    }
}
