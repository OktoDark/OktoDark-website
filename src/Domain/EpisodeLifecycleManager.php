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

use App\Entity\Episode;
use App\Entity\TV;
use App\Enum\WatchStatus;

class EpisodeLifecycleManager
{
    public function __construct(
        private readonly SeasonLifecycleManager $seasonLifecycle,
    ) {
    }

    /**
     * Mark a single episode as watched and propagate progress.
     */
    public function markEpisodeWatched(Episode $episode): void
    {
        $episode->setStartDate($episode->getStartDate() ?? new \DateTimeImmutable());
        $episode->setEndDate(new \DateTimeImmutable());
        $episode->setStatus(WatchStatus::COMPLETED);

        $season = $episode->getRelatedSeason();
        if ($season) {
            $this->seasonLifecycle->updateSeasonAndTv($season);
        }
    }

    /**
     * Undo watched state for a single episode and propagate progress.
     */
    public function undoEpisodeWatched(Episode $episode): void
    {
        $episode->setEndDate(null);
        $episode->setStatus(WatchStatus::IN_PROGRESS);

        $season = $episode->getRelatedSeason();
        if ($season) {
            $this->seasonLifecycle->updateSeasonAndTv($season);
        }
    }

    /**
     * Auto-advance: find next episode in same season.
     */
    public function findNextInSeason(Episode $episode): ?Episode
    {
        $season = $episode->getRelatedSeason();
        if (!$season) {
            return null;
        }

        $episodes = $season->getEpisodes();
        $currentNumber = $episode->getEpisodeNumber();

        foreach ($episodes as $ep) {
            if ($ep->getEpisodeNumber() === $currentNumber + 1) {
                return $ep;
            }
        }

        return null;
    }

    /**
     * Auto-advance: find first episode of next season.
     */
    public function findFirstOfNextSeason(Episode $episode): ?Episode
    {
        $season = $episode->getRelatedSeason();
        if (!$season) {
            return null;
        }

        $tv = $season->getRelatedTv();
        if (!$tv) {
            return null;
        }

        $currentSeasonNumber = $season->getSeasonNumber();
        $nextSeason = null;

        foreach ($tv->getSeasons() as $s) {
            if ($s->getSeasonNumber() === $currentSeasonNumber + 1) {
                $nextSeason = $s;
                break;
            }
        }

        if (!$nextSeason) {
            return null;
        }

        foreach ($nextSeason->getEpisodes() as $ep) {
            if ($ep->getEpisodeNumber() === 1) {
                return $ep;
            }
        }

        return null;
    }

    /**
     * Determine if the entire TV show is completed (all episodes watched).
     */
    public function isTvCompleted(TV $tv): bool
    {
        foreach ($tv->getSeasons() as $season) {
            foreach ($season->getEpisodes() as $ep) {
                if (!$ep->isWatched()) {
                    return false;
                }
            }
        }

        return true;
    }
}
