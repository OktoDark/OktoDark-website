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

use App\Entity\MediaMetadata;
use App\Entity\Movie;
use App\Entity\User;
use App\Enum\WatchStatus;
use App\Repository\MovieRepository;
use App\Service\MetadataEnricher;
use App\Service\TmdbService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MoviesController extends AbstractController
{
    #[Route('/tracker/movies', name: 'app_tracker_movies')]
    public function index(MovieRepository $movieRepo, TmdbService $tmdb): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $movies = $movieRepo->findUserMovies($user);

        $newReleases = $tmdb->discoverMovies('now_playing', 12);
        $recommendations = $tmdb->discoverMovies('popular', 12);
        $topRated = $tmdb->discoverMovies('top_rated', 12);
        $comingSoon = $tmdb->discoverMovies('upcoming', 12);

        return $this->render('@theme/tracker/movies/index.html.twig', [
            'movies' => $movies,
            'newReleases' => $newReleases,
            'recommendations' => $recommendations,
            'topRated' => $topRated,
            'comingSoon' => $comingSoon,
        ]);
    }

    #[Route('/tracker/movies/add', name: 'app_tracker_movies_add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $metadataId = $request->request->get('metadata_id');
        $metadata = $em->getRepository(MediaMetadata::class)->find($metadataId);

        if (!$metadata) {
            throw $this->createNotFoundException('Metadata not found');
        }

        $movie = new Movie();
        $movie->setUser($user);
        $movie->setMediaMetadata($metadata);
        $movie->setStatus(WatchStatus::PLANNING);
        $movie->setProgress(0);

        $em->persist($movie);
        $em->flush();

        return $this->redirectToRoute('app_tracker_movies');
    }

    #[Route('/tracker/movies/{id}/update', name: 'app_tracker_movies_update', methods: ['POST'])]
    public function update(Movie $movie, Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($movie->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $status = WatchStatus::from($request->request->get('status'));
        $progress = max(0, (int) $request->request->get('progress'));

        $movie->setStatus($status);
        $movie->setProgress($progress);
        $movie->setScore($request->request->get('score'));
        $movie->setNotes($request->request->get('notes'));

        $em->flush();

        return $this->redirectToRoute('app_tracker_movies');
    }

    #[Route('/tracker/movies/{id}/delete', name: 'app_tracker_movies_delete', methods: ['POST'])]
    public function delete(Movie $movie, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($movie->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($movie);
        $em->flush();

        return $this->redirectToRoute('app_tracker_movies');
    }

    #[Route('/tracker/movies/{id}/complete', name: 'app_tracker_movies_complete', methods: ['POST'])]
    public function complete(Movie $movie, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($movie->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $movie->setStatus(WatchStatus::COMPLETED);
        $movie->setEndDate(new \DateTimeImmutable());

        $em->flush();

        return $this->redirectToRoute('app_tracker_movies');
    }

    #[Route('/tracker/movies/{id}/start', name: 'app_tracker_movies_start', methods: ['POST'])]
    public function start(Movie $movie, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($movie->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $movie->setStatus(WatchStatus::IN_PROGRESS);
        $movie->setStartDate(new \DateTimeImmutable());

        $em->flush();

        return $this->redirectToRoute('app_tracker_movies');
    }

    #[Route('/api/tracker/movies', name: 'api_tracker_movies_list', methods: ['GET'])]
    public function apiListMovies(MovieRepository $movieRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $movies = $movieRepo->findUserMovies($user);

        $data = array_map(static function (Movie $m) {
            return [
                'id' => $m->getId(),
                'title' => $m->getMediaMetadata()->getTitle(),
                'status' => $m->getStatusLabel(),
                'progress' => $m->getProgress(),
                'score' => $m->getFormattedScore(),
            ];
        }, $movies);

        return $this->json($data);
    }

    #[Route('/api/tracker/movies', name: 'api_tracker_movies_create', methods: ['POST'])]
    public function apiCreateMovie(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $metadataId = $request->request->get('metadata_id');
        $metadata = $em->getRepository(MediaMetadata::class)->find($metadataId);

        if (!$metadata) {
            return $this->json(['error' => 'Metadata not found'], 404);
        }

        $movie = new Movie();
        $movie->setUser($user);
        $movie->setMediaMetadata($metadata);

        $em->persist($movie);
        $em->flush();

        return $this->json(['id' => $movie->getId()], 201);
    }

    #[Route('/tracker/movies/import/tmdb', name: 'app_tracker_movies_import_tmdb', methods: ['POST'])]
    public function importTmdbMovie(
        Request $request,
        MetadataEnricher $enricher,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $tmdbId = $request->request->get('tmdb_id');
        $meta = $enricher->importMovie($tmdbId);

        $movie = new Movie();
        $movie->setUser($user);
        $movie->setMediaMetadata($meta);

        $em->persist($movie);
        $em->flush();

        return $this->redirectToRoute('app_tracker_movies');
    }

    #[Route('/api/metadata/tmdb/movie', methods: ['POST'])]
    public function apiImportTmdbMovie(
        Request $request,
        MetadataEnricher $enricher,
    ): Response {
        $meta = $enricher->importMovie($request->request->get('tmdb_id'));

        return $this->json([
            'id' => $meta->getId(),
            'title' => $meta->getTitle(),
            'image' => $meta->getImage(),
        ], 201);
    }
}
