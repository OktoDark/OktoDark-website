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

use App\Domain\EpisodeLifecycleManager;
use App\Domain\SeasonLifecycleManager;
use App\Domain\TvLifecycleManager;
use App\Entity\User;
use App\Repository\EpisodeRepository;
use App\Repository\SeasonRepository;
use App\Repository\TVRepository;

class EpisodeService
{
    public function __construct(
        private readonly EpisodeRepository $episodeRepo,
        private readonly SeasonRepository $seasonRepo,
        private readonly TVRepository $tvRepo,
        private readonly EpisodeLifecycleManager $episodeLifecycle,
        private readonly SeasonLifecycleManager $seasonLifecycle,
        private readonly TvLifecycleManager $tvLifecycle,
    ) {
    }

    /**
     * Mark a single episode watched + auto-advance.
     */
    public function markEpisodeWatched(User $user, int $showId, int $seasonNumber, int $episodeNumber): void
    {
        // Metadata-driven lookup
        $episode = $this->episodeRepo->findEpisodeForUser($user, $showId, $seasonNumber, $episodeNumber);
        if (!$episode) {
            return;
        }

        // Mark episode watched (lifecycle-driven)
        $this->episodeLifecycle->markEpisodeWatched($episode);

        // Update season + TV progress
        $season = $episode->getRelatedSeason();
        $this->seasonLifecycle->updateSeasonAndTv($season);

        // Auto-advance to next episode
        $next = $this->episodeLifecycle->findNextInSeason($episode);

        if (!$next) {
            // Try first episode of next season
            $next = $this->episodeLifecycle->findFirstOfNextSeason($episode);
        }

        // If no next episode exists → TV is completed
        if (!$next) {
            $tv = $season->getRelatedTv();
            $this->tvLifecycle->markCompleted($tv);
        }
    }

    /**
     * Mark entire season watched.
     */
    public function markSeasonWatched(User $user, int $showId, int $seasonNumber): void
    {
        // Metadata-driven lookup
        $episodes = $this->episodeRepo->findEpisodesInSeason($user, $showId, $seasonNumber);
        if (!$episodes) {
            return;
        }

        // Mark all episodes watched
        foreach ($episodes as $episode) {
            $this->episodeLifecycle->markEpisodeWatched($episode);
        }

        // Update season + TV progress
        $season = $episodes[0]->getRelatedSeason();
        $this->seasonLifecycle->updateSeasonAndTv($season);
    }

    /**
     * Undo last watched episode.
     */
    public function undoLastWatched(User $user, int $showId): void
    {
        // Metadata-driven lookup
        $last = $this->episodeRepo->findLastWatchedEpisode($user, $showId);
        if (!$last) {
            return;
        }

        // Undo episode watched
        $this->episodeLifecycle->undoEpisodeWatched($last);

        // Update season + TV progress
        $season = $last->getRelatedSeason();
        $this->seasonLifecycle->updateSeasonAndTv($season);
    }
}
