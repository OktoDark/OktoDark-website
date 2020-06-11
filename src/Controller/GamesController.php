<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 13.01.2020, 06:50
 */

namespace App\Controller;

use App\Repository\OurGamesRepository;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GamesController extends AbstractController
{
    /**
     * @Route("/games", methods="GET", name="games")
     */
    public function games(SettingsRepository $settings, OurGamesRepository $ourGames): Response
    {
        $AllGames = $ourGames->findAll();
        $selectSettings = $settings->findAll();

        return $this->render('@theme/games.html.twig', [
            'games' => $AllGames,
            'settings' => $selectSettings,
        ]);
    }
}
