<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Domain;

use App\Entity\TV;
use App\Enum\WatchStatus;

class TvLifecycleManager
{
    /**
     * Recompute TV progress and status based on all seasons/episodes.
     */
    public function recomputeProgressAndStatus(TV $tv): void
    {
        $seasons = $tv->getSeasons();
        $totalEpisodes = 0;
        $watchedEpisodes = 0;
        $lastWatched = null;

        foreach ($seasons as $season) {
            foreach ($season->getEpisodes() as $ep) {
                ++$totalEpisodes;
                if ($ep->isWatched()) {
                    ++$watchedEpisodes;
                    $endDate = $ep->getEndDate();
                    if ($endDate && ($lastWatched === null || $endDate > $lastWatched)) {
                        $lastWatched = $endDate;
                    }
                }
            }
        }

        $progress = $totalEpisodes > 0
            ? (int) round(($watchedEpisodes / $totalEpisodes) * 100)
            : 0;

        $tv->setProgress($progress);

        if (100 === $progress) {
            $tv->setStatus(WatchStatus::COMPLETED);
        } elseif ($progress > 0) {
            $tv->setStatus(WatchStatus::IN_PROGRESS);
        } else {
            $tv->setStatus(WatchStatus::PLANNING);
        }

        if ($lastWatched) {
            $tv->setProgressedAt($lastWatched);
        }
    }

    public function markCompleted(TV $tv): void
    {
        $tv->setProgress(100);
        $tv->setStatus(WatchStatus::COMPLETED);
    }

    public function resetToPlanning(TV $tv): void
    {
        $tv->setProgress(0);
        $tv->setStatus(WatchStatus::PLANNING);
    }
}
