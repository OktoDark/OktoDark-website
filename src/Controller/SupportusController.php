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

use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SupportusController extends AbstractController
{
    #[Route('/supportus', methods: ['GET'], name: 'supportus')]
    public function supportus(SettingsRepository $settings): Response
    {
        return $this->render('@theme/supportus.html.twig', [
            'settings' => $settings->findAll(),
        ]);
    }

    #[Route('/bepatron', methods: ['GET'], name: 'bepatron')]
    public function bepatron(SettingsRepository $settings): Response
    {
        return $this->render('@theme/bepatron.html.twig', [
            'settings' => $settings->findAll(),
        ]);
    }

    #[Route('/bedonor', methods: ['GET'], name: 'bedonor')]
    public function bedonor(SettingsRepository $settings): Response
    {
        return $this->render('@theme/bedonor.html.twig', [
            'settings' => $settings->findAll(),
        ]);
    }
}
