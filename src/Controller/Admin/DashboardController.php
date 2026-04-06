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

use App\Entity\AnalyticsPageView;
use App\Repository\AnalyticsContentViewRepository;
use App\Repository\AnalyticsEventRepository;
use App\Repository\AnalyticsPageViewRepository;
use App\Repository\AnalyticsSessionRepository;
use App\Repository\NewsRepository;
use App\Repository\RegistrationWaitlistRepository;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepo,
        private NewsRepository $newsRepo,
        private ServicesRepository $servicesRepo,
        private RegistrationWaitlistRepository $waitlistRepo,
        private SettingsProvider $settingsProvider,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        AnalyticsSessionRepository $sessionRepo,
        AnalyticsPageViewRepository $pageRepo,
        AnalyticsEventRepository $eventRepo,
        AnalyticsContentViewRepository $contentRepo,
    ): Response {
        $stats = [
            'users' => $this->userRepo->count([]),
            'news' => $this->newsRepo->count([]),
            'services' => $this->servicesRepo->count([]),
            'waitlist' => $this->waitlistRepo->count([]),
            'registration_enabled' => $this->settingsProvider->isRegistrationEnabled(),

            'active_sessions' => $sessionRepo->countActiveSessions(),
            'page_views_today' => $pageRepo->countToday(),
            'errors_today' => $eventRepo->countToday(),

            'traffic_last_7_days' => $pageRepo->trafficLastDays(7),
            'hourly_heatmap' => $pageRepo->hourlyHeatmap(),
            'devices' => $sessionRepo->countByDevice(),
            'countries' => $sessionRepo->countByCountry(),
            'browsers' => $sessionRepo->countByBrowser(),
            'referrers' => $pageRepo->topReferrers(8),
            'top_routes' => $pageRepo->topRoutes(8),
            'top_news' => $contentRepo->topContent('news', 8),
            'top_ips' => $sessionRepo->findTopIps(),
            'recent_sessions' => $sessionRepo->findRecentSessions(),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/analytics/track', name: 'analytics_track', methods: ['POST'])]
    public function track(Request $request, EntityManagerInterface $em): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new Response('', 204);
        }

        $sessionId = $request->getSession()->getId();

        $repo = $em->getRepository(AnalyticsPageView::class);
        $view = $repo->findLatestBySessionAndUrl($sessionId, $data['url']);

        if ($view && isset($data['duration'])) {
            $view->setDuration($data['duration']);
            $em->flush();
        }

        return new Response('', 204);
    }

    #[Route('/admin/live-traffic')]
    public function liveTraffic(AnalyticsPageViewRepository $repo): JsonResponse
    {
        return new JsonResponse([
            'views' => $repo->countLastMinuteViews(),
        ]);
    }
}
