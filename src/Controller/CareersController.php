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

use App\Repository\CareersRepository;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CareersController extends AbstractController
{
    /**
     * @Route("/careers", methods="GET", name="careers")
     */
    public function careers(SettingsRepository $settings, CareersRepository $careers): Response
    {
        $selectSettings = $settings->findAll();
        $showCareers = $careers->findAll();

        return $this->render('@theme/careers.html.twig', [
            'settings' => $selectSettings,
            'careers' => $showCareers,
        ]);
    }
}
