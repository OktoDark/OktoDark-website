<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 13.01.2020, 06:50
 */

namespace App\Controller;

use App\Repository\AssetsRepository;
use App\Repository\SettingsRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AssetsController
 * @package App\Controller
 *
 * @IsGranted("ROLE_ADMIN")
 */
class AssetsController extends AbstractController
{
    /**
     * @Route("/assets", methods="GET", name="assets_index")
     *
     * @return Response
     */
    public function home(SettingsRepository $settings, AssetsRepository $assets): Response
    {
        $findAllAssets = $assets->findAll();
        $figurecompatible = $assets->showFigureCompatible();
        $selectSettings = $settings->findAll();

        return $this->render('@theme/assets.html.twig', [
            'assets' => $findAllAssets,
            'figurec' => $figurecompatible,
            'settings' => $selectSettings,
        ]);
    }
}
