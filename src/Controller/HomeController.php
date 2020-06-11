<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
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
 */
class HomeController extends AbstractController
{
    /**
     * @Route("/", methods="GET", name="home_index")
     * @Route("/home", methods="GET", name="home")
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
