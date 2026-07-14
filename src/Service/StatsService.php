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

use App\Entity\User;
use App\Enum\WatchStatus;
use App\Repository\AnimeRepository;
use App\Repository\EpisodeRepository;
use App\Repository\GameRepository;
use App\Repository\MovieRepository;
use App\Repository\SeasonRepository;
use App\Repository\TVRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class StatsService
{
    public function __construct(
        private SeasonRepository $seasonRepo,
        private EpisodeRepository $episodeRepo,
        private MovieRepository $movieRepo,
        private AnimeRepository $animeRepo,
        private GameRepository $gameRepo,
        private TVRepository $tvRepo,
        private CacheInterface $cache,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * GLOBAL TRACKER STATS (cached).
     */
    public function getGlobalStats(User $user): array
    {
        $cacheKey = \sprintf('stats.global.%d', $user->getId());

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(300);

            return [
                'tv' => $this->getTvStats($user),
                'movies' => $this->getMovieStats($user),
                'anime' => $this->getAnimeStats($user),
                'games' => $this->getGameStats($user),
                'totals' => $this->getTotals($user),
                'watching' => $this->countWatching($user),
            ];
        });
    }

    /**
     * DASHBOARD STATS (premium).
     */
    public function buildDashboardStats(User $user): array
    {
        return [
            'summary' => [
                'tv' => $this->getTvStats($user),
                'watching' => $this->countWatching($user),
                'totals' => [
                    'completedMedia' => $this->getTotals($user)['completedMedia'],
                ],
            ],

            'progressOverTime' => $this->getProgressOverTime($user),
            'episodesPerMonth' => $this->getEpisodesPerMonth($user),
            'topShows' => $this->getTopShows($user),
            'topGenres' => $this->getTopGenres($user),
            'dailyHeatmap' => $this->getDailyHeatmap($user),
        ];
    }

    /**
     * WATCHING COUNT (accurate).
     */
    private function countWatching(User $user): int
    {
        return
            $this->tvRepo->countShowsByStatus($user, WatchStatus::IN_PROGRESS) +
            $this->movieRepo->countUserWatchingMovies($user) +
            $this->animeRepo->countUserWatchingAnime($user) +
            $this->gameRepo->countUserPlayingGames($user);
    }

    /**
     * TV SHOW STATS.
     */
    public function getTvStats(User $user): array
    {
        $totalSeries = $this->tvRepo->countUserShows($user);
        $totalEpisodes = $this->episodeRepo->countTotalEpisodesForUser($user);
        $watchedEpisodes = $this->episodeRepo->countWatchedEpisodesForUser($user);

        $totalMinutes = $this->episodeRepo->sumWatchedRuntime($user);
        $hoursWatched = round($totalMinutes / 60, 1);

        $completionRate = $totalEpisodes > 0
            ? round(($watchedEpisodes / $totalEpisodes) * 100, 1)
            : 0;

        return [
            'totalSeries' => $totalSeries,
            'totalEpisodes' => $totalEpisodes,
            'watchedEpisodes' => $watchedEpisodes,
            'completionRate' => $completionRate,
            'hoursWatched' => $hoursWatched,
        ];
    }

    /**
     * MOVIE STATS.
     */
    public function getMovieStats(User $user): array
    {
        $totalMovies = $this->movieRepo->countUserMovies($user);
        $completed = $this->movieRepo->countUserCompletedMovies($user);

        $completionRate = $totalMovies > 0
            ? round(($completed / $totalMovies) * 100, 1)
            : 0;

        return [
            'totalMovies' => $totalMovies,
            'completed' => $completed,
            'completionRate' => $completionRate,
        ];
    }

    /**
     * ANIME STATS.
     */
    public function getAnimeStats(User $user): array
    {
        $totalAnime = $this->animeRepo->countUserAnime($user);
        $completed = $this->animeRepo->countUserCompletedAnime($user);

        $completionRate = $totalAnime > 0
            ? round(($completed / $totalAnime) * 100, 1)
            : 0;

        return [
            'totalAnime' => $totalAnime,
            'completed' => $completed,
            'completionRate' => $completionRate,
        ];
    }

    /**
     * GAME STATS.
     */
    public function getGameStats(User $user): array
    {
        $totalGames = $this->gameRepo->countUserGames($user);
        $completed = $this->gameRepo->countUserCompletedGames($user);

        $completionRate = $totalGames > 0
            ? round(($completed / $totalGames) * 100, 1)
            : 0;

        return [
            'totalGames' => $totalGames,
            'completed' => $completed,
            'completionRate' => $completionRate,
        ];
    }

    /**
     * TOTALS ACROSS ALL MEDIA.
     */
    public function getTotals(User $user): array
    {
        $tv = $this->getTvStats($user);
        $movies = $this->getMovieStats($user);
        $anime = $this->getAnimeStats($user);
        $games = $this->getGameStats($user);

        $totalMedia =
            $tv['totalSeries'] +
            $movies['totalMovies'] +
            $anime['totalAnime'] +
            $games['totalGames'];

        $completedMedia =
            $movies['completed'] +
            $anime['completed'] +
            $games['completed'] +
            $this->tvRepo->countShowsByStatus($user, WatchStatus::COMPLETED);

        $globalCompletionRate = $totalMedia > 0
            ? round(($completedMedia / $totalMedia) * 100, 1)
            : 0;

        return [
            'totalMedia' => $totalMedia,
            'completedMedia' => $completedMedia,
            'globalCompletionRate' => $globalCompletionRate,
        ];
    }

    /**
     * PROGRESS OVER TIME (line chart).
     */
    private function getProgressOverTime(User $user): array
    {
        return $this->episodeRepo->getEpisodesWatchedByDay($user);
    }

    /**
     * EPISODES PER MONTH (bar chart).
     */
    private function getEpisodesPerMonth(User $user): array
    {
        return $this->episodeRepo->getEpisodesWatchedByMonth($user);
    }

    /**
     * TOP SHOWS (card list).
     */
    private function getTopShows(User $user): array
    {
        return $this->tvRepo->getTopShowsByWatchTime($user);
    }

    /**
     * TOP GENRES (card list).
     */
    private function getTopGenres(User $user): array
    {
        return $this->tvRepo->getTopGenres($user);
    }

    /**
     * DAILY HEATMAP (GitHub-style).
     */
    private function getDailyHeatmap(User $user): array
    {
        return $this->episodeRepo->getDailyHeatmap($user);
    }

    public function queueStatsRefresh(User $user): void
    {
        $this->bus->dispatch(new \App\Message\RefreshStatsMessage($user->getId()));
    }

    public function refreshStats(User $user): array
    {
        $cacheKey = \sprintf('stats.global.%d', $user->getId());
        $this->cache->delete($cacheKey);

        return $this->getGlobalStats($user);
    }
}
