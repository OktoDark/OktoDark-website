<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\AnimeRepository;
use App\Repository\GameRepository;
use App\Repository\SeasonRepository;
use App\Service\EpisodeService;
use App\Service\SeasonService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/tracker')]
class TrackingApiController extends AbstractController
{
    /**
     * Initialize tracking API dependencies for entity manager and episode/season services.
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EpisodeService $episodeService,
        private readonly SeasonService $seasonService,
    ) {
    }

    /**
     * Mark the next unwatched episode of a season as watched for the current user.
     */
    #[Route('/season/{id}/increment-episode', name: 'api_tracker_season_inc', methods: ['POST'])]
    public function incrementSeasonEpisode(
        int $id,
        SeasonRepository $seasonRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $season = $seasonRepo->find($id);

        if (
            !$season
            || $season->getRelatedTv()->getUser() !== $user
        ) {
            return new JsonResponse(['error' => 'Unauthorized or entry not found.'], 403);
        }

        // Mark next episode watched using lifecycle logic
        $nextEpisode = $season->getNextUnwatchedEpisode();

        if (!$nextEpisode) {
            return new JsonResponse(['error' => 'No remaining episodes'], 400);
        }

        $this->episodeService->markEpisodeWatched(
            $user,
            $season->getRelatedTv()->getId(),
            $season->getSeasonNumber(),
            $nextEpisode->getEpisodeNumber()
        );

        return new JsonResponse([
            'success' => true,
            'newProgress' => $season->getProgress(),
            'status' => $season->getStatus()->value,
        ]);
    }

    /**
     * Increment playtime for a tracked game by 30 minutes.
     */
    #[Route('/game/{id}/add-playtime', name: 'api_tracker_game_time', methods: ['POST'])]
    public function addGamePlaytime(
        int $id,
        GameRepository $gameRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $game = $gameRepo->find($id);

        if (!$game || $game->getUser() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized.'], 403);
        }

        // Increment playtime by 30 minutes
        $game->setProgress($game->getProgress() + 30);
        $game->setProgressedAt(new \DateTimeImmutable());

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'newProgressValue' => $game->getProgress(),
            'formattedTime' => $game->getFormattedProgress(),
        ]);
    }

    /**
     * Increment anime watch progress by one episode.
     */
    #[Route('/anime/{id}/increment', name: 'api_tracker_anime_inc', methods: ['POST'])]
    public function incrementAnimeProgress(
        int $id,
        AnimeRepository $animeRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $anime = $animeRepo->find($id);

        if (!$anime || $anime->getUser() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized or entry not found.'], 403);
        }

        $anime->setProgress($anime->getProgress() + 1);
        $anime->setProgressedAt(new \DateTimeImmutable());

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'newProgress' => $anime->getProgress(),
            'status' => $anime->getStatus()->value,
        ]);
    }
}
