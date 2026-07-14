<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Domain\SeasonLifecycleManager;
use App\Domain\TvLifecycleManager;
use App\Entity\Season;
use App\Entity\User;
use App\Enum\WatchStatus;
use App\Repository\EpisodeRepository;
use App\Repository\SeasonRepository;
use App\Repository\TVRepository;

class SeasonService
{
    public function __construct(
        private readonly SeasonRepository $seasonRepo,
        private readonly EpisodeRepository $episodeRepo,
        private readonly TVRepository $tvRepo,
        private readonly SeasonLifecycleManager $seasonLifecycle,
        private readonly TvLifecycleManager $tvLifecycle,
    ) {
    }

    /**
     * Recompute season progress + propagate to TV.
     */
    public function updateSeason(Season $season): void
    {
        $this->seasonLifecycle->updateSeasonAndTv($season);
    }

    /**
     * Explicitly mark season completed.
     */
    public function markSeasonCompleted(Season $season): void
    {
        $season->setProgress(100);
        $season->setStatus(WatchStatus::COMPLETED);

        $this->seasonLifecycle->propagateToTv($season->getRelatedTv());
    }

    /**
     * Season-level statistics for a user.
     */
    public function getStats(User $user): array
    {
        // Total unique TV shows
        $totalSeries = $this->tvRepo->countUserShows($user);

        // Total episodes
        $totalEpisodes = $this->episodeRepo->countTotalEpisodesForUser($user);

        // Watched episodes
        $watchedEpisodes = $this->episodeRepo->countWatchedEpisodesForUser($user);

        // Runtime (metadata-driven)
        $totalMinutes = $this->episodeRepo->sumWatchedRuntime($user);
        $hoursWatched = round($totalMinutes / 60, 1);
        $totalDays = round($hoursWatched / 8, 1);

        // Status counts
        $watching = $this->seasonRepo->countGroupedShows($user, WatchStatus::IN_PROGRESS);
        $completed = $this->seasonRepo->countGroupedShows($user, WatchStatus::COMPLETED);
        $planning = $this->seasonRepo->countGroupedShows($user, WatchStatus::PLANNING);
        $onHold = $this->seasonRepo->countGroupedShows($user, WatchStatus::ON_HOLD);
        $dropped = $this->seasonRepo->countGroupedShows($user, WatchStatus::DROPPED);

        return [
            'totalSeries' => $totalSeries,
            'totalEpisodes' => $totalEpisodes,
            'totalEpisodesWatched' => $watchedEpisodes,
            'hoursWatched' => $hoursWatched,
            'totalDays' => $totalDays,

            'watching' => $watching,
            'completed' => $completed,
            'planning' => $planning,
            'onHold' => $onHold,
            'dropped' => $dropped,

            'avgEpisodeRating' => 0, // optional future feature
        ];
    }
}
