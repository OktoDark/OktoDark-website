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
use App\Repository\EpisodeRepository;
use App\Service\EpisodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EpisodeController extends AbstractController
{
    #[Route('/tracker/episode/{id}', name: 'app_tracker_episode')]
    public function show(int $id, EpisodeRepository $episodeRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $episode = $episodeRepo->find($id);

        if (
            !$episode
            || $episode->getRelatedSeason()->getRelatedTv()->getUser() !== $user
        ) {
            throw $this->createNotFoundException('Episode not found.');
        }

        return $this->render('@theme/tracker/episode/show.html.twig', [
            'episode' => $episode,
        ]);
    }

    #[Route('/tracker/episode/{id}/watch', name: 'app_tracker_episode_watch', methods: ['POST'])]
    public function watch(
        int $id,
        EpisodeRepository $episodeRepo,
        EpisodeService $episodeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $episode = $episodeRepo->find($id);
        if (!$episode) {
            return $this->json(['error' => 'Episode not found'], 404);
        }

        $episodeService->markEpisodeWatched(
            $user,
            $episode->getRelatedSeason()->getRelatedTv()->getId(),
            $episode->getSeasonNumber(),
            $episode->getEpisodeNumber()
        );

        return $this->json(['status' => 'episode_watched']);
    }

    #[Route('/tracker/episode/{id}/unwatch', name: 'app_tracker_episode_unwatch', methods: ['POST'])]
    public function unwatch(
        int $id,
        EpisodeRepository $episodeRepo,
        EpisodeService $episodeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $episode = $episodeRepo->find($id);
        if (!$episode) {
            return $this->json(['error' => 'Episode not found'], 404);
        }

        $episodeService->undoLastWatched(
            $user,
            $episode->getRelatedSeason()->getRelatedTv()->getId()
        );

        return $this->json(['status' => 'episode_unwatched']);
    }

    /**
     * Called via JS:
     * fetch('{{ path("app_mark_episode_watched") }}', { method: 'POST', body: { id, season, episode } }).
     */
    #[Route('/tracker/episode/mark-watched', name: 'app_mark_episode_watched', methods: ['POST'])]
    public function markEpisodeWatched(
        Request $request,
        EpisodeService $episodeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $showId = $request->request->getInt('id');
        $season = $request->request->getInt('season');
        $episode = $request->request->getInt('episode');

        if (!$showId || !$season || !$episode) {
            return $this->json(['error' => 'Missing parameters'], 400);
        }

        $episodeService->markEpisodeWatched($user, $showId, $season, $episode);

        return $this->json(['status' => 'episode_watched']);
    }

    #[Route('/tracker/season/mark-watched', name: 'app_mark_season_watched', methods: ['POST'])]
    public function markSeasonWatched(
        Request $request,
        EpisodeService $episodeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $showId = $request->request->getInt('id');
        $seasonNumber = $request->request->getInt('season');

        if (!$showId || !$seasonNumber) {
            return $this->json(['error' => 'Missing parameters'], 400);
        }

        $episodeService->markSeasonWatched($user, $showId, $seasonNumber);

        return $this->json(['status' => 'season_watched']);
    }

    #[Route('/tracker/episode/undo-watched', name: 'app_undo_episode_watched', methods: ['POST'])]
    public function undoEpisodeWatched(
        Request $request,
        EpisodeService $episodeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $showId = $request->request->getInt('showId');

        if (!$showId) {
            return $this->json(['error' => 'Missing show id'], 400);
        }

        $episodeService->undoLastWatched($user, $showId);

        return $this->json(['status' => 'episode_unwatched']);
    }
}
