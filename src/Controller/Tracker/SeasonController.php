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
use App\Repository\SeasonRepository;
use App\Service\EpisodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SeasonController extends AbstractController
{
    #[Route('/tracker/season/{id}', name: 'app_tracker_season')]
    public function show(
        int $id,
        SeasonRepository $seasonRepo,
        EpisodeRepository $episodeRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $season = $seasonRepo->find($id);

        if (
            !$season
            || $season->getRelatedTv()->getUser() !== $user
        ) {
            throw $this->createNotFoundException('Season not found.');
        }

        $episodes = $episodeRepo->findBy([
            'relatedSeason' => $season,
        ]);

        return $this->render('@theme/tracker/season/show.html.twig', [
            'season' => $season,
            'episodes' => $episodes,
        ]);
    }

    #[Route('/tracker/season/{id}/watch-all', name: 'app_tracker_season_watch_all', methods: ['POST'])]
    public function watchAll(
        int $id,
        SeasonRepository $seasonRepo,
        EpisodeService $episodeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $season = $seasonRepo->find($id);

        if (
            !$season
            || $season->getRelatedTv()->getUser() !== $user
        ) {
            return $this->json(['error' => 'Season not found'], 404);
        }

        $episodeService->markSeasonWatched(
            $user,
            $season->getRelatedTv()->getId(),
            $season->getSeasonNumber()
        );

        return $this->json(['status' => 'season_watched']);
    }

    #[Route('/tracker/season/{id}/unwatch-all', name: 'app_tracker_season_unwatch_all', methods: ['POST'])]
    public function unwatchAll(
        int $id,
        SeasonRepository $seasonRepo,
        EpisodeService $episodeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $season = $seasonRepo->find($id);

        if (
            !$season
            || $season->getRelatedTv()->getUser() !== $user
        ) {
            return $this->json(['error' => 'Season not found'], 404);
        }

        // Undo all episodes in this season
        foreach ($season->getEpisodes() as $ep) {
            $episodeService->undoLastWatched(
                $user,
                $season->getRelatedTv()->getId()
            );
        }

        return $this->json(['status' => 'season_unwatched']);
    }
}
