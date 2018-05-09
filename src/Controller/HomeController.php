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

use App\Entity\News;
use App\Repository\NewsRepository;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Driver\Connection;

class HomeController extends Controller
{
    public function home(Connection $connection): Response
    {
        $latestNews = $connection->fetchAll('SELECT * FROM news');
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/home.html.twig', ['news' => $latestNews, 'settings' => $selectSettings]);
    }
}
