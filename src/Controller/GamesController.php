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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GamesController extends AbstractController
{
    #[Route('/games', name: 'games', methods: ['GET'])]
    public function games(OurGamesRepository $ourGames): Response
    {
        return $this->render('@theme/games.html.twig', [
            'games' => $ourGames->findAll(),
        ]);
    }
}
