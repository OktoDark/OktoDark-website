<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
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
