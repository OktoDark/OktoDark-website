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

use App\Repository\AnalyticsSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ActiveUsersController extends AbstractController
{
    #[Route('/admin/active-users', name: 'admin_active_users')]
    public function activeUsers(AnalyticsSessionRepository $repo): JsonResponse
    {
        $active = $repo->countActiveSessions(); // your own method

        return new JsonResponse([
            'active' => $active,
        ]);
    }
}
