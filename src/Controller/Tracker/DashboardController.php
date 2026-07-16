<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Tracker;

use App\Entity\User;
use App\Enum\WatchStatus;
use App\Repository\AnimeRepository;
use App\Repository\EpisodeRepository;
use App\Repository\SeasonRepository;
use App\Repository\TVRepository;
use App\Repository\UserMessageRepository;
use App\Service\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/tracker', name: 'app_tracking_dashboard')]
    public function index(
        Request $request,
        TVRepository $tvRepo,
        SeasonRepository $seasonRepo,
        EpisodeRepository $episodeRepo,
        AnimeRepository $animeRepo,
        UserMessageRepository $msgRepo,
        EntityManagerInterface $em,
        StatsService $statsService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isFullyActive()) {
            throw new AccessDeniedException('Your account must be fully verified and active to access the media tracker.');
        }

        // -------------------------------------------
        // CONTINUE WATCHING (OPTIMIZED DB LIMITS)
        // -------------------------------------------
        $cwPerPage = 7;

        // TV Show Continue Watching
        $cwTvPage = $request->query->getInt('cwTvPage', 1);
        $totalTvContinue = $tvRepo->countContinueWatching($user);
        $cwTvTotalPages = (int) ceil($totalTvContinue / $cwPerPage);
        $cwTvPage = max(1, min($cwTvPage, $cwTvTotalPages > 0 ? $cwTvTotalPages : 1));
        $cwTvOffset = ($cwTvPage - 1) * $cwPerPage;
        $continueWatchingTv = $tvRepo->findContinueWatching($user, $cwTvOffset, $cwPerPage);

        // Anime Continue Watching
        $cwAnimePage = $request->query->getInt('cwAnimePage', 1);
        $totalAnimeContinue = $animeRepo->countContinueWatching($user);
        $cwAnimeTotalPages = (int) ceil($totalAnimeContinue / $cwPerPage);
        $cwAnimePage = max(1, min($cwAnimePage, $cwAnimeTotalPages > 0 ? $cwAnimeTotalPages : 1));
        $cwAnimeOffset = ($cwAnimePage - 1) * $cwPerPage;
        $continueWatchingAnime = $animeRepo->findContinueWatching($user, $cwAnimeOffset, $cwPerPage);

        $continueWatching = array_merge($continueWatchingTv, $continueWatchingAnime);

        // -------------------------------------------
        // NEXT EPISODES
        // -------------------------------------------
        $nextEpisodes = $episodeRepo->findNextEpisodes($user);

        // -------------------------------------------
        // RECENTLY WATCHED
        // -------------------------------------------
        $recentWatched = $episodeRepo->findRecentlyWatched($user);

        // -------------------------------------------
        // STATUS FILTER
        // -------------------------------------------
        $statusParam = $request->query->get('status');
        $statusFilter = null;

        if ($statusParam && 'all' !== $statusParam) {
            $statusFilter = match (mb_strtolower($statusParam)) {
                'watching' => WatchStatus::IN_PROGRESS,
                'completed' => WatchStatus::COMPLETED,
                'planning' => WatchStatus::PLANNING,
                'paused' => WatchStatus::PAUSED,
                'dropped' => WatchStatus::DROPPED,
                default => null,
            };
        }

        // -------------------------------------------
        // SORT + SEARCH
        // -------------------------------------------
        $sortFilter = $request->query->get('sort', 'created_at');
        $searchQuery = $request->query->get('search');

        // -------------------------------------------
        // ANIME LIST
        // -------------------------------------------
        $animeList = $animeRepo->getMediaList($user, $statusFilter, $sortFilter, $searchQuery);
        $animeList = \array_slice($animeList, 0, 100);

        // -------------------------------------------
        // PAGINATION FOR TV SHOWS GRID
        // -------------------------------------------
        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('perPage', 20);

        $totalShows = $seasonRepo->countGroupedShows($user, $statusFilter, $searchQuery);

        $lastPage = (int) ceil($totalShows / $perPage);
        $lastPage = max($lastPage, 1);
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        $tvShowsData = $seasonRepo->findGroupedSeasons(
            $user,
            $statusFilter,
            $searchQuery,
            $perPage,
            $offset
        );

        // -------------------------------------------
        // SMART PAGINATOR
        // -------------------------------------------
        $paginator = new class($totalShows, $perPage, $page) {
            public function __construct(
                private int $totalItems,
                private int $pageSize,
                private int $currentPage,
            ) {
            }

            public function hasToPaginate(): bool
            {
                return $this->totalItems > $this->pageSize;
            }

            public function getLastPage(): int
            {
                return (int) ceil($this->totalItems / $this->pageSize);
            }

            public function getPages(): array
            {
                $pages = [];
                $lastPage = $this->getLastPage();
                $current = $this->currentPage;

                $pages[] = 1;

                for ($i = $current - 2; $i <= $current + 2; ++$i) {
                    if ($i > 1 && $i < $lastPage) {
                        $pages[] = $i;
                    }
                }

                if ($lastPage > 1) {
                    $pages[] = $lastPage;
                }

                $pages = array_unique($pages);
                sort($pages);

                return $pages;
            }

            public function isCurrentPage(int $page): bool
            {
                return $this->currentPage === $page;
            }
        };

        // -------------------------------------------
        // TOAST MESSAGES
        // -------------------------------------------
        $messages = $msgRepo->createQueryBuilder('m')
            ->where('m.user = :user')
            ->andWhere('m.shownAt IS NULL')
            ->setParameter('user', $user)
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
        foreach ($messages as $msg) {
            $msg->setShownAt(new \DateTimeImmutable());
        }
        $em->flush();

        // -------------------------------------------
        // STATS
        // -------------------------------------------
        $stats = $statsService->getGlobalStats($user);

        return $this->render('@theme/tracker/dashboard.html.twig', [
            'continueWatching' => $continueWatching,
            'nextEpisodes' => $nextEpisodes,
            'recentWatched' => $recentWatched,
            'tvShows' => $paginator,
            'tvShowsData' => $tvShowsData,
            'perPage' => $perPage,
            'animeList' => $animeList,
            'currentStatus' => $statusParam,
            'currentSort' => $sortFilter,
            'searchQuery' => $searchQuery,
            'toastMessages' => $messages,
            'stats' => $stats,

            // TV Continue Pagination variables
            'cwTvPage' => $cwTvPage,
            'cwTvTotalPages' => $cwTvTotalPages,
            'totalTvContinue' => $totalTvContinue,

            // Anime Continue Pagination variables
            'cwAnimePage' => $cwAnimePage,
            'cwAnimeTotalPages' => $cwAnimeTotalPages,
            'totalAnimeContinue' => $totalAnimeContinue,
        ]);
    }
}
