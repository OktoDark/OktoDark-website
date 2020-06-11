<?php

/**
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 13.01.2020, 06:50
 */

namespace App\Controller;

use App\Repository\NewsRepository;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/")
 *
 * Class HomeController
 * @package App\Controller
 */
class HomeController extends AbstractController
{
    /**
     * @Route("/", methods="GET", name="home_index")
     * @Route("/home", methods="GET", name="home")
     *
     * @return Response
     */
    public function home(SettingsRepository $settings, NewsRepository $news): Response
    {
        $latestNews = $news->findAll();
        $selectSettings = $settings->findAll();

        return $this->render('@theme/home.html.twig', [
            'news' => $latestNews,
            'settings' => $selectSettings,
        ]);
    }
}
