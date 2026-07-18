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
use App\Repository\SeasonRepository;
use App\Repository\TVRepository;
use App\Service\TmdbService;
use App\Service\TvMazeService;
use App\Security\Attribute\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TvShowController extends AbstractController
{
    #[Route('/tracker/tv/{id}', name: 'app_tracking_tv_show')]
    #[Permission('tracker.tv.view')]
    public function show(
        int $id,
        TVRepository $tvRepo,
        SeasonRepository $seasonRepo,
        TvMazeService $maze,
        TmdbService $tmdb,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Fetch TV show and validate ownership
        $tv = $tvRepo->find($id);

        if (!$tv || $tv->getUser() !== $user) {
            throw $this->createNotFoundException('TV Show not found.');
        }

        // Fetch seasons for this TV show
        $seasons = $seasonRepo->findBy([
            'relatedTv' => $tv,
        ]);

        // Sort seasons by season number, then episodes by episode number (ascending)
        usort($seasons, static fn ($a, $b) => ($a->getSeasonNumber() ?? 0) <=> ($b->getSeasonNumber() ?? 0));

        foreach ($seasons as $season) {
            $episodes = $season->getEpisodes()->toArray();
            usort($episodes, static fn ($a, $b) => ($a->getEpisodeNumber() ?? 0) <=> ($b->getEpisodeNumber() ?? 0));
            $season->setEpisodes($episodes);
        }

        // Resolve show-level cast (stored first, otherwise fetched from TVMaze on demand)
        $cast = $tv->getCast() ?? [];

        if (empty($cast)) {
            $mazeId = $tv->getMediaMetadata()?->getExternalId();

            if (!$mazeId) {
                $results = $maze->searchShows((string) $tv->getTitle());
                $mazeId = $results[0]['metaId'] ?? null;
            }

            if ($mazeId) {
                $cast = array_map(
                    static fn (array $c) => [
                        'name' => $c['person']['name'] ?? null,
                        'character' => $c['character']['name'] ?? null,
                        'image' => $c['person']['image']['medium'] ?? null,
                    ],
                    $maze->getCast((int) $mazeId) ?? []
                );
            }
        }

        // Resolve show-level trailer (stored first, otherwise fetched from TMDB on demand)
        $trailer = $tv->getTrailer() ?? null;

        if (!$trailer) {
            $tmdbId = $tv->getMediaMetadata()?->getTmdbId();

            if (!$tmdbId) {
                $show = $tmdb->searchShow((string) $tv->getTitle(), $tv->getMediaMetadata()?->getYear());
                $tmdbId = $show['id'] ?? null;
            }

            if ($tmdbId) {
                $trailer = $tmdb->getShowVideos((int) $tmdbId);

                // Fallback: stored TMDB id had no videos, try a title search
                if (!$trailer) {
                    $show = $tmdb->searchShow((string) $tv->getTitle(), $tv->getMediaMetadata()?->getYear());
                    if (!empty($show['id'])) {
                        $trailer = $tmdb->getShowVideos((int) $show['id']);
                    }
                }
            }
        }

        return $this->render('@theme/tracker/series/show_details.html.twig', [
            'tv' => $tv,
            'seasons' => $seasons,
            'cast' => $cast,
            'trailer' => $trailer,
        ]);
    }
}
