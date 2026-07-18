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
use App\Security\Attribute\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AssetsController extends AbstractController
{
    /**
     * Displays the admin assets management page.
     */
    #[Route('/assets', name: 'assets_index', methods: ['GET'])]
    #[Permission('assets_index')]
    public function index(SettingsRepository $settings, AssetsRepository $assets): Response
    {
        return $this->render('@theme/assets.html.twig', [
            'assets' => $assets->findAll(),
            'figurec' => $assets->findFigureCompatible(), // renamed for clarity
            'settings' => $settings->findAll(),
        ]);
    }
}
