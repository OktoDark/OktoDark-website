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

use App\Entity\Game;
use App\Entity\MediaMetadata;
use App\Enum\WatchStatus;
use App\Repository\GameRepository;
use App\Security\Attribute\Permission;
use App\Service\MetadataEnricher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GamesController extends AbstractController
{
    #[Route('/tracker/games', name: 'app_tracker_games')]
    #[Permission('tracker.games.view')]
    public function index(GameRepository $gameRepo): Response
    {
        $games = $gameRepo->findBy(['user' => $this->getUser()]);

        return $this->render('@theme/tracker/games/index.html.twig', [
            'games' => $games,
        ]);
    }

    #[Route('/tracker/games/add', name: 'app_tracker_games_add', methods: ['POST'])]
    #[Permission('tracker.games.add')]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $metadataId = $request->request->get('metadata_id');

        $metadata = $em->getRepository(MediaMetadata::class)->find($metadataId);
        if (!$metadata) {
            throw $this->createNotFoundException('Metadata not found');
        }

        $game = new Game();
        $game->setUser($user);
        $game->setMediaMetadata($metadata);
        $game->setStatus(WatchStatus::PLANNING);
        $game->setProgress(0);

        $em->persist($game);
        $em->flush();

        return $this->redirectToRoute('app_tracker_games');
    }

    #[Route('/tracker/games/{id}/update', name: 'app_tracker_games_update', methods: ['POST'])]
    public function update(Game $game, Request $request, EntityManagerInterface $em): Response
    {
        if ($game->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $game->setStatus(WatchStatus::from($request->request->get('status')));
        $game->setProgress((int) $request->request->get('progress'));
        $game->setScore($request->request->get('score'));
        $game->setNotes($request->request->get('notes'));

        $em->flush();

        return $this->redirectToRoute('app_tracker_games');
    }

    #[Route('/tracker/games/{id}/delete', name: 'app_tracker_games_delete', methods: ['POST'])]
    public function delete(Game $game, EntityManagerInterface $em): Response
    {
        if ($game->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($game);
        $em->flush();

        return $this->redirectToRoute('app_tracker_games');
    }

    #[Route('/tracker/games/{id}/complete', name: 'app_tracker_games_complete', methods: ['POST'])]
    public function complete(Game $game, EntityManagerInterface $em): Response
    {
        if ($game->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $game->setStatus(WatchStatus::COMPLETED);
        $game->setEndDate(new \DateTime());

        $em->flush();

        return $this->redirectToRoute('app_tracker_games');
    }

    #[Route('/tracker/games/import/igdb', name: 'app_tracker_games_import_igdb', methods: ['POST'])]
    #[Permission('tracker.games.import')]
    public function importIgdbGame(
        Request $request,
        MetadataEnricher $enricher,
        EntityManagerInterface $em,
    ): Response {
        $igdbId = $request->request->get('igdb_id');

        if (!$igdbId) {
            throw $this->createNotFoundException('IGDB ID missing.');
        }

        // Import metadata
        $meta = $enricher->importGame($igdbId);

        // Create Game entry for the current user
        $game = new Game();
        $game->setUser($this->getUser());
        $game->setMediaMetadata($meta);

        $em->persist($game);
        $em->flush();

        return $this->redirectToRoute('app_tracker_games');
    }

    #[Route('/api/tracker/games', name: 'api_tracker_games_list', methods: ['GET'])]
    public function apiListGames(GameRepository $repo): Response
    {
        $games = $repo->findBy(['user' => $this->getUser()]);

        return $this->json(array_map(static fn ($g) => [
            'id' => $g->getId(),
            'title' => $g->getTitle(),
            'status' => $g->getStatusLabel(),
            'progress' => $g->getProgress(),
            'score' => $g->getFormattedScore(),
        ], $games));
    }

    #[Route('/api/tracker/games', name: 'api_tracker_games_create', methods: ['POST'])]
    public function apiCreateGames(Request $request, EntityManagerInterface $em): Response
    {
        $metadataId = $request->request->get('metadata_id');
        $metadata = $em->getRepository(MediaMetadata::class)->find($metadataId);

        if (!$metadata) {
            return $this->json(['error' => 'Metadata not found'], 404);
        }

        $game = new Game();
        $game->setUser($this->getUser());
        $game->setMediaMetadata($metadata);

        $em->persist($game);
        $em->flush();

        return $this->json(['id' => $game->getId()], 201);
    }

    #[Route('/api/metadata/igdb/game', name: 'api_metadata_igdb_game', methods: ['POST'])]
    public function apiImportIgdbGame(
        Request $request,
        MetadataEnricher $enricher,
        EntityManagerInterface $em,
    ): Response {
        $igdbId = $request->request->get('igdb_id');

        if (!$igdbId) {
            return $this->json(['error' => 'IGDB ID missing'], 400);
        }

        $meta = $enricher->importGame($igdbId);

        return $this->json([
            'id' => $meta->getId(),
            'title' => $meta->getTitle(),
            'image' => $meta->getImage(),
        ], 201);
    }
}
