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

use App\Repository\AssetsRepository;
use App\Repository\SettingsRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class AssetsController.
 */
#[IsGranted('ROLE_ADMIN')]
final class AssetsController extends AbstractController
{
    #[Route('/assets', methods: ['GET'], name: 'assets_index')]
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
