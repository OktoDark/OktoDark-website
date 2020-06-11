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

use App\Repository\ModsRepository;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ModsController extends AbstractController
{
    /**
     * @Route("/mods", methods="GET", name="mods")
     *
     * @return Response
     */
    public function mods(SettingsRepository $settings, ModsRepository $mods): Response
    {
        $latestMods = $mods->findAll();
        $selectSettings = $settings->findAll();

        return $this->render('@theme/mods.html.twig', [
            'mods' => $latestMods,
            'settings' => $selectSettings,
        ]);
    }
}
