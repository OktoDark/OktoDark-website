<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller;

use App\Repository\OurGamesRepository;
use App\Security\Attribute\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GamesController extends AbstractController
{
    /**
     * Lists all available games.
     */
    #[Route('/games', name: 'games', methods: ['GET'])]
    #[Permission('games.view')]
    public function index(OurGamesRepository $repository): Response
    {
        return $this->render('@theme/games/games.html.twig', [
            'games' => $repository->findAll(),
        ]);
    }

    /**
     * Displays details for a specific game identified by its slug.
     */
    #[Route('/games/{slug}', name: 'app_game_show', methods: ['GET'])]
    #[Permission('games.view.details')]
    public function show(string $slug, OurGamesRepository $repository): Response
    {
        $game = $repository->findOneBy(['shortNameSlug' => $slug]);

        if (!$game) {
            throw $this->createNotFoundException('The game does not exist');
        }

        return $this->render('@theme/games/game_details.html.twig', [
            'game' => $game,
        ]);
    }
}
