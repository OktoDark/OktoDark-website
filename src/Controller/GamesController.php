<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 09.05.2018 12:20
 */

namespace App\Controller;

use App\Entity\Settings;
use App\Entity\OurGames;
use App\Repository\SettingsRepository;
use App\Repository\OurGamesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GamesController extends AbstractController
{
    /**
     * @Route("/games", methods={"GET"}, name="games")
     * @param Connection $connection
     * @return Response
     */
    public function games(Connection $connection): Response
    {
        $AllGames = $connection->fetchAll('SELECT * FROM our_games');
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/games.html.twig', ['games' => $AllGames, 'settings' => $selectSettings]);
    }
}
