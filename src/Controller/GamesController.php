<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 17.01.2018 03:13
 */

namespace App\Controller;

use App\Entity\Settings;
use App\Entity\OurGames;
use App\Repository\SettingsRepository;
use App\Repository\OurGamesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\HttpFoundation\Response;

class GamesController extends Controller
{
    public function games(Connection $connection): Response
    {
        $AllGames = $connection->fetchAll('SELECT * FROM our_games');
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/games.html.twig', ['games' => $AllGames, 'settings' => $selectSettings]);
    }
}
