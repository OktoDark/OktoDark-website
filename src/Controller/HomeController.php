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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Driver\Connection;
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
     * @Route("/", methods={"GET"}, name="home_index")
     * @Route("/home", methods={"GET"}, name="home")
     *
     * @return Response
     */
    public function home(SettingsRepository $settings, NewsRepository $news): Response
    {
        $latestNews = $news->findAll();
        $selectSettings = $settings->findAll();

        return $this->render('@theme/home.html.twig', ['news' => $latestNews, 'settings' => $selectSettings]);
    }
}
