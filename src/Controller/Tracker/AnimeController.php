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

use App\Entity\Anime;
use App\Entity\MediaMetadata;
use App\Enum\WatchStatus;
use App\Repository\AnimeRepository;
use App\Service\AnilistService;
use App\Service\MetadataEnricher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnimeController extends AbstractController
{
    #[Route('/tracker/anime', name: 'app_tracker_anime')]
    public function index(AnimeRepository $animeRepo, AnilistService $anilist): Response
    {
        $anime = $animeRepo->findBy(['user' => $this->getUser()]);

        $newReleases = $anilist->discoverAnime('new_releases', 12);
        $recommendations = $anilist->discoverAnime('trending', 12);
        $topRated = $anilist->discoverAnime('top_rated', 12);
        $comingSoon = $anilist->discoverAnime('upcoming', 12);

        return $this->render('@theme/tracker/anime/index.html.twig', [
            'anime' => $anime,
            'newReleases' => $newReleases,
            'recommendations' => $recommendations,
            'topRated' => $topRated,
            'comingSoon' => $comingSoon,
        ]);
    }

    #[Route('/tracker/anime/add', name: 'app_tracker_anime_add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $metadataId = $request->request->get('metadata_id');

        $metadata = $em->getRepository(MediaMetadata::class)->find($metadataId);
        if (!$metadata) {
            throw $this->createNotFoundException('Metadata not found');
        }

        $anime = new Anime();
        $anime->setUser($user);
        $anime->setMediaMetadata($metadata);
        $anime->setStatus(WatchStatus::PLANNING);
        $anime->setProgress(0);

        $em->persist($anime);
        $em->flush();

        return $this->redirectToRoute('app_tracker_anime');
    }

    #[Route('/tracker/anime/{id}/update', name: 'app_tracker_anime_update', methods: ['POST'])]
    public function update(Anime $anime, Request $request, EntityManagerInterface $em): Response
    {
        if ($anime->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $anime->setStatus(WatchStatus::from($request->request->get('status')));
        $anime->setProgress((int) $request->request->get('progress'));
        $anime->setScore($request->request->get('score'));
        $anime->setNotes($request->request->get('notes'));

        $em->flush();

        return $this->redirectToRoute('app_tracker_anime');
    }

    #[Route('/tracker/anime/{id}/delete', name: 'app_tracker_anime_delete', methods: ['POST'])]
    public function delete(Anime $anime, EntityManagerInterface $em): Response
    {
        if ($anime->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($anime);
        $em->flush();

        return $this->redirectToRoute('app_tracker_anime');
    }

    #[Route('/tracker/anime/{id}/complete', name: 'app_tracker_anime_complete', methods: ['POST'])]
    public function complete(Anime $anime, EntityManagerInterface $em): Response
    {
        if ($anime->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $anime->setStatus(WatchStatus::COMPLETED);
        $anime->setEndDate(new \DateTime());

        $em->flush();

        return $this->redirectToRoute('app_tracker_anime');
    }

    #[Route('/tracker/anime/import/anilist', name: 'app_tracker_anime_import_anilist', methods: ['POST'])]
    public function importAnilistAnime(
        Request $request,
        MetadataEnricher $enricher,
        EntityManagerInterface $em,
    ): Response {
        $anilistId = $request->request->get('anilist_id');

        if (!$anilistId) {
            throw $this->createNotFoundException('AniList ID missing.');
        }

        // Import metadata
        $meta = $enricher->importAnime($anilistId);

        // Create Anime entry for the current user
        $anime = new Anime();
        $anime->setUser($this->getUser());
        $anime->setMediaMetadata($meta);

        $em->persist($anime);
        $em->flush();

        return $this->redirectToRoute('app_tracker_anime');
    }

    #[Route('/api/tracker/anime', name: 'api_tracker_anime_list', methods: ['GET'])]
    public function apiListAnime(AnimeRepository $repo): Response
    {
        $anime = $repo->findBy(['user' => $this->getUser()]);

        return $this->json(array_map(static fn ($a) => [
            'id' => $a->getId(),
            'title' => $a->getTitle(),
            'status' => $a->getStatusLabel(),
            'progress' => $a->getProgress(),
            'score' => $a->getFormattedScore(),
        ], $anime));
    }

    #[Route('/api/tracker/anime', name: 'api_tracker_anime_create', methods: ['POST'])]
    public function apiCreateAnime(Request $request, EntityManagerInterface $em): Response
    {
        $metadataId = $request->request->get('metadata_id');
        $metadata = $em->getRepository(MediaMetadata::class)->find($metadataId);

        if (!$metadata) {
            return $this->json(['error' => 'Metadata not found'], 404);
        }

        $anime = new Anime();
        $anime->setUser($this->getUser());
        $anime->setMediaMetadata($metadata);

        $em->persist($anime);
        $em->flush();

        return $this->json(['id' => $anime->getId()], 201);
    }

    #[Route('/api/metadata/anilist/anime', name: 'api_metadata_anilist_anime', methods: ['POST'])]
    public function apiImportAnilistAnime(
        Request $request,
        MetadataEnricher $enricher,
        EntityManagerInterface $em,
    ): Response {
        $anilistId = $request->request->get('anilist_id');

        if (!$anilistId) {
            return $this->json(['error' => 'AniList ID missing'], 400);
        }

        $meta = $enricher->importAnime($anilistId);

        return $this->json([
            'id' => $meta->getId(),
            'title' => $meta->getTitle(),
            'image' => $meta->getImage(),
        ], 201);
    }
}
