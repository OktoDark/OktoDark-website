<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Tracker;

use App\Entity\User;
use App\Security\Attribute\Permission;
use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class StatsController extends AbstractController
{
    #[Route('/tracker/stats', name: 'app_tracker_stats')]
    #[Permission('tracker.stats', group: 'Tracker', label: 'View Tracker Stats')]
    public function index(StatsService $statsService): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isFullyActive()) {
            throw new AccessDeniedException('Your account must be fully verified and active to access the media tracker.');
        }

        $stats = $statsService->buildDashboardStats($user);

        return $this->render('@theme/tracker/stats/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }
}
