<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 26.08.2018 21:22
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
     * @Route("/careers", methods={"GET"}, name="careers")
     *
     * @return \Symfony\Component\HttpFoundation\Response
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
