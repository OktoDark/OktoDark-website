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
use App\Repository\GameRepository;
use App\Repository\MovieRepository;
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
        MovieRepository $movieRepo,
        AnimeRepository $animeRepo,
        GameRepository $gameRepo,
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
        // CONTINUE WATCHING
        // -------------------------------------------
        $continueWatching = $tvRepo->findContinueWatching($user);

        $cwPage = max(1, $request->query->getInt('cwPage', 1));
        $cwPerPage = 4;

        $totalCW = \count($continueWatching);
        $cwTotalPages = max(1, (int) ceil($totalCW / $cwPerPage));

        $continueWatching = \array_slice(
            $continueWatching,
            ($cwPage - 1) * $cwPerPage,
            $cwPerPage
        );

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
        // PAGINATION FOR TV SHOWS GRID
        // -------------------------------------------
        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('perPage', 20);
        $offset = ($page - 1) * $perPage;

        $totalShows = $seasonRepo->countGroupedShows($user, $statusFilter, $searchQuery);

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
        // OTHER MEDIA TYPES
        // -------------------------------------------
        $movies = $movieRepo->getMediaList($user, $statusFilter, $sortFilter, $searchQuery);
        $anime = $animeRepo->getMediaList($user, $statusFilter, $sortFilter, $searchQuery);
        $games = $gameRepo->getMediaList($user, $statusFilter, $sortFilter, $searchQuery);

        // -------------------------------------------
        // TOAST MESSAGES
        // -------------------------------------------
        $messages = $msgRepo->findBy(['user' => $user, 'shownAt' => null]);
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
            'cwCurrentPage' => $cwPage,
            'cwTotalPages' => $cwTotalPages,
            'movies' => $movies,
            'anime' => $anime,
            'games' => $games,
            'currentStatus' => $statusParam,
            'currentSort' => $sortFilter,
            'searchQuery' => $searchQuery,
            'toastMessages' => $messages,
            'stats' => $stats,
        ]);
    }
}
