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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SupportusController extends AbstractController
{
    #[Route('/supportus', name: 'supportus', methods: ['GET'])]
    public function supportus(): Response
    {
        return $this->render('@theme/supportus.html.twig', [
        ]);
    }

    #[Route('/bepatron', name: 'bepatron', methods: ['GET'])]
    public function bepatron(): Response
    {
        return $this->render('@theme/bepatron.html.twig', [
        ]);
    }

    #[Route('/bedonor', name: 'bedonor', methods: ['GET'])]
    public function bedonor(): Response
    {
        return $this->render('@theme/bedonor.html.twig', [
        ]);
    }
}
