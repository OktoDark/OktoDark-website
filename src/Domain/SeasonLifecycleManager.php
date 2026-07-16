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

use App\Entity\Season;
use App\Entity\TV;
use App\Enum\WatchStatus;

class SeasonLifecycleManager
{
    public function computeSeasonProgress(Season $season): int
    {
        $episodes = $season->getEpisodes();
        $total = \count($episodes);
        $watched = 0;

        foreach ($episodes as $ep) {
            if ($ep->isWatched()) {
                ++$watched;
            }
        }

        return $total > 0 ? (int) round(($watched / $total) * 100) : 0;
    }

    public function updateSeasonStatus(Season $season): void
    {
        $progress = $this->computeSeasonProgress($season);
        $season->setProgress($progress);

        if (100 === $progress) {
            $season->setStatus(WatchStatus::COMPLETED);
        } elseif ($progress > 0) {
            $season->setStatus(WatchStatus::IN_PROGRESS);
        } else {
            $season->setStatus(WatchStatus::PLANNING);
        }
    }

    public function propagateToTv(TV $tv): void
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
                    if ($endDate && (null === $lastWatched || $endDate > $lastWatched)) {
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

    public function updateSeasonAndTv(Season $season): void
    {
        $this->updateSeasonStatus($season);

        $tv = $season->getRelatedTv();
        if ($tv) {
            $this->propagateToTv($tv);
        }
    }
}
