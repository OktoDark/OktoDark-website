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

#[Permission('games.view', group: 'Games', label: 'View games')]
final class GamesController extends AbstractController
{
    #[Route('/games', name: 'games', methods: ['GET'])]
    public function index(OurGamesRepository $repository): Response
    {
        return $this->render('@theme/games/games.html.twig', [
            'games' => $repository->findAll(),
        ]);
    }

    #[Route('/games/{slug}', name: 'app_game_show', methods: ['GET'])]
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
