<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 25.08.2018 19:49
 */

namespace App\Controller;

use App\Repository\SettingsRepository;
use App\Repository\OurGamesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GamesController extends AbstractController
{
    /**
     * @Route("/games", methods={"GET"}, name="games")
     *
     * @return Response
     */
    public function games(SettingsRepository $settings, OurGamesRepository $ourGames): Response
    {
        $AllGames = $ourGames->findAll();
        $selectSettings = $settings->findAll();

        return $this->render('@theme/games.html.twig', ['games' => $AllGames, 'settings' => $selectSettings]);
    }
}
