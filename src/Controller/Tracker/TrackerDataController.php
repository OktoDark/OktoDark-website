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
use App\Entity\BoardGame;
use App\Entity\Book;
use App\Entity\Comic;
use App\Entity\Episode;
use App\Entity\Game;
use App\Entity\Manga;
use App\Entity\MediaMetadata;
use App\Entity\Movie;
use App\Entity\Season;
use App\Entity\TV;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/tracker/data', name: 'app_tracker_data_')]
class TrackerDataController extends AbstractController
{
    /**
     * Exports all tracker data for the authenticated user as a downloadable JSON file.
     *
     * Collects movies, TV shows with seasons and episodes, anime, games, manga,
     * comics, books and board games, then streams the result as a JSON attachment
     * so the user can back up or migrate their tracking data.
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request, EntityManagerInterface $em): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = [
            'exported_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'user_id' => $user->getId(),
            'movies' => $this->exportMovies($em, $user),
            'tv' => $this->exportTv($em, $user),
            'anime' => $this->exportAnime($em, $user),
            'games' => $this->exportGames($em, $user),
            'manga' => $this->exportManga($em, $user),
            'comics' => $this->exportComics($em, $user),
            'books' => $this->exportBooks($em, $user),
            'board_games' => $this->exportBoardGames($em, $user),
        ];

        $json = \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return new StreamedResponse(function () use ($json): void {
            echo $json;
        }, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="tracker-export-'.(new \DateTimeImmutable())->format('Y-m-d').'.json"',
        ]);
    }

    /**
     * Permanently deletes all tracker data for the authenticated user.
     *
     * Removes tracking rows across all media tables (episodes and seasons first
     * to respect foreign-key constraints), then redirects back to the import
     * dashboard with a success flash message.
     */
    #[Route('/clean', name: 'clean', methods: ['POST'])]
    public function clean(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('tracker_clean', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $conn = $em->getConnection();
        $userId = $user->getId();

        $conn->executeStatement('DELETE FROM tracking_episode WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_season WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_tv WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_movie WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_anime WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_game WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_manga WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_comic WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_book WHERE user_id = :uid', ['uid' => $userId]);
        $conn->executeStatement('DELETE FROM tracking_boardgame WHERE user_id = :uid', ['uid' => $userId]);

        $this->addFlash('success', 'Your tracker data has been permanently deleted.');

        return $this->redirectToRoute('app_import_dashboard');
    }

    private function exportMovies(EntityManagerInterface $em, User $user): array
    {
        return $this->exportMedia($em, $user, Movie::class);
    }

    private function exportTv(EntityManagerInterface $em, User $user): array
    {
        $repo = $em->getRepository(TV::class);
        $shows = $repo->createQueryBuilder('t')
            ->innerJoin('t.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($shows as $tv) {
            $meta = $tv->getMediaMetadata();
            $entry = $this->mediaEntry($tv, $meta);
            $entry['seasons'] = $this->exportSeasonsForTv($em, $tv);
            $result[] = $entry;
        }

        return $result;
    }

    private function exportSeasonsForTv(EntityManagerInterface $em, TV $tv): array
    {
        $seasonRepo = $em->getRepository(Season::class);
        $episodeRepo = $em->getRepository(Episode::class);

        $seasons = $seasonRepo->createQueryBuilder('s')
            ->innerJoin('s.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('s.relatedTv = :tv')
            ->setParameter('tv', $tv)
            ->orderBy('meta.seasonNumber', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($seasons as $season) {
            $meta = $season->getMediaMetadata();
            $entry = $this->mediaEntry($season, $meta);
            $entry['season_number'] = $meta?->getSeasonNumber();

            $episodes = $episodeRepo->createQueryBuilder('e')
                ->innerJoin('e.mediaMetadata', 'emeta')
                ->addSelect('emeta')
                ->where('e.relatedSeason = :season')
                ->setParameter('season', $season)
                ->orderBy('emeta.episodeNumber', 'ASC')
                ->getQuery()
                ->getResult();

            $entry['episodes'] = array_map(static function (Episode $ep): array {
                $emeta = $ep->getMediaMetadata();

                return [
                    'id' => $ep->getId(),
                    'episode_number' => $emeta?->getEpisodeNumber(),
                    'title' => $emeta?->getTitle(),
                    'status' => $ep->getStatus()?->value,
                    'startDate' => $ep->getStartDate()?->format(\DateTimeInterface::ATOM),
                    'endDate' => $ep->getEndDate()?->format(\DateTimeInterface::ATOM),
                ];
            }, $episodes);

            $result[] = $entry;
        }

        return $result;
    }

    private function exportAnime(EntityManagerInterface $em, User $user): array
    {
        return $this->exportMedia($em, $user, Anime::class);
    }

    private function exportGames(EntityManagerInterface $em, User $user): array
    {
        return $this->exportMedia($em, $user, Game::class);
    }

    private function exportManga(EntityManagerInterface $em, User $user): array
    {
        return $this->exportMedia($em, $user, Manga::class);
    }

    private function exportComics(EntityManagerInterface $em, User $user): array
    {
        return $this->exportMedia($em, $user, Comic::class);
    }

    private function exportBooks(EntityManagerInterface $em, User $user): array
    {
        return $this->exportMedia($em, $user, Book::class);
    }

    private function exportBoardGames(EntityManagerInterface $em, User $user): array
    {
        return $this->exportMedia($em, $user, BoardGame::class);
    }

    private function exportMedia(EntityManagerInterface $em, User $user, string $class): array
    {
        $repo = $em->getRepository($class);

        $items = $repo->createQueryBuilder('m')
            ->innerJoin('m.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(function ($item): array {
            $meta = $item->getMediaMetadata();

            return $this->mediaEntry($item, $meta);
        }, $items);
    }

    private function mediaEntry(object $entity, ?MediaMetadata $meta): array
    {
        return [
            'id' => method_exists($entity, 'getId') ? $entity->getId() : null,
            'media_id' => $meta?->getMediaId(),
            'source' => $meta?->getSource()?->value,
            'media_type' => $meta?->getMediaType()?->value,
            'title' => $meta?->getTitle(),
            'original_title' => $meta?->getOriginalTitle(),
            'image' => $meta?->getImage(),
            'overview' => $meta?->getOverview(),
            'genres' => $meta?->getGenres(),
            'runtime' => $meta?->getRuntime(),
            'release_date' => $meta?->getReleaseDate()?->format(\DateTimeInterface::ATOM),
            'year' => $meta?->getYear(),
            'external_id' => $meta?->getExternalId(),
            'tmdb_id' => $meta?->getTmdbId(),
            'igdb_id' => $meta?->getIgdbId(),
            'anilist_id' => $meta?->getAnilistId(),
            'tracking' => [
                'status' => method_exists($entity, 'getStatus') ? $entity->getStatus()?->value : null,
                'progress' => method_exists($entity, 'getProgress') ? $entity->getProgress() : null,
                'score' => method_exists($entity, 'getScore') ? $entity->getScore() : null,
                'start_date' => method_exists($entity, 'getStartDate') ? $entity->getStartDate()?->format(\DateTimeInterface::ATOM) : null,
                'end_date' => method_exists($entity, 'getEndDate') ? $entity->getEndDate()?->format(\DateTimeInterface::ATOM) : null,
                'notes' => method_exists($entity, 'getNotes') ? $entity->getNotes() : null,
                'created_at' => method_exists($entity, 'getCreatedAt') ? $entity->getCreatedAt()?->format(\DateTimeInterface::ATOM) : null,
                'progressed_at' => method_exists($entity, 'getProgressedAt') ? $entity->getProgressedAt()?->format(\DateTimeInterface::ATOM) : null,
            ],
        ];
    }
}
