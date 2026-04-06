<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SystemHealthController extends AbstractController
{
    #[Route('/admin/system-health', name: 'admin_system_health')]
    public function index(): Response
    {
        $health = [
            'mailer' => 'unknown', // later: real check
            'cache' => 'ok',
            'logs' => 'ok',
        ];

        return $this->render('@theme/admin/system_health.html.twig', [
            'health' => $health,
        ]);
    }
}