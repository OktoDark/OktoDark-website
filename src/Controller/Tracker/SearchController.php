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

use App\Service\TmdbService;
use App\Service\TvMazeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/tracker/search', name: 'app_tracker_search')]
    public function search(
        Request $request,
        TmdbService $tmdb,
        TvMazeService $maze,
    ): Response {
        $query = mb_trim((string) $request->query->get('q', ''));

        // Prevent empty queries from hitting external APIs
        if ('' === $query) {
            return $this->render('@theme/tracker/search/results.html.twig', [
                'query' => '',
                'tmdb' => [],
                'maze' => [],
            ]);
        }

        // External API search
        $tmdbResults = $tmdb->searchShows($query);
        $mazeResults = $maze->searchShows($query);

        return $this->render('@theme/tracker/search/results.html.twig', [
            'query' => $query,
            'tmdb' => $tmdbResults,
            'maze' => $mazeResults,
        ]);
    }
}
