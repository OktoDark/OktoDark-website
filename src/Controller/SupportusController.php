<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 25.08.2018 19:49
 */

namespace App\Controller;

use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SupportusController extends AbstractController
{
    /**
     * @Route("/bepatron", methods={"GET"}, name="bepatron")
     */
    public function bepatron(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/bepatron.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/bedonator", methods={"GET"}, name="bedonator")
     */
    public function bedonator(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/bedonator.html.twig', ['settings' => $selectSettings]);
    }
}