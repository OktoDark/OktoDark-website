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

use App\Repository\AnalyticsPageViewRepository;
use App\Repository\AnalyticsSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AnalyticsController extends AbstractController
{
    #[Route('/live-traffic', name: 'admin_live_traffic')]
    public function liveTraffic(AnalyticsPageViewRepository $repo): JsonResponse
    {
        return new JsonResponse([
            'views' => $repo->countLastMinuteViews(),
        ]);
    }

    #[Route('/active-users', name: 'admin_active_users')]
    public function activeUsers(AnalyticsSessionRepository $repo): JsonResponse
    {
        return new JsonResponse([
            'active' => $repo->countActiveSessions(),
        ]);
    }
}