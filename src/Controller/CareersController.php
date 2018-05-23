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

use App\Repository\CareersRepository;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CareersController extends Controller
{
    /**
     * @Route("/careers", methods={"GET"}, name="careers")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function careers(SettingsRepository $settings, CareersRepository $careers): Response
    {
        $selectSettings = $settings->findAll();
        $showCareers = $careers->findAll();

        return $this->render('@theme/careers.html.twig', ['settings' => $selectSettings, 'careers' => $showCareers]);
    }
}
