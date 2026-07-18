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
use App\Repository\CareerApplicationRepository;
use App\Repository\NewsRepository;
use App\Repository\RegistrationWaitlistRepository;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
use App\Security\Attribute\Permission;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    /**
     * Initialize dashboard dependencies for entity counts and settings.
     */
    public function __construct(
        private UserRepository $userRepo,
        private NewsRepository $newsRepo,
        private ServicesRepository $servicesRepo,
        private RegistrationWaitlistRepository $waitlistRepo,
        private CareerApplicationRepository $careerRepo,
        private SettingsProvider $settingsProvider,
    ) {
    }

    /**
     * Render the admin dashboard with aggregated analytics and content statistics.
     *
     * Deduplicates recent analytics sessions by a signature built from their
     * IP/browser/OS/device/country attributes, then composes counts, traffic
     * trends, device/browser/referrer breakdowns and content rankings via the
     * analytics repositories.
     */
    #[Route('/admin', name: 'admin_dashboard')]
    #[Permission('admin.dashboard.index')]
    public function index(
        AnalyticsSessionRepository $sessionRepo,
        AnalyticsPageViewRepository $pageRepo,
        AnalyticsEventRepository $eventRepo,
        AnalyticsContentViewRepository $contentRepo,
    ): Response {
        $rawSessions = $sessionRepo->findRecentSessions();

        $unique = [];

        foreach ($rawSessions as $s) {
            // Build unique signature from entity getters
            $key = implode('|', [
                $s->getIp() ?? '',
                $s->getBrowser() ?? '',
                $s->getBrowserVersion() ?? '',
                $s->getOs() ?? '',
                $s->getOsVersion() ?? '',
                $s->getDevice() ?? '',
                $s->getDeviceModel() ?? '',
                $s->getCountry() ?? '',
            ]);

            // Keep only the most recent timestamp
            if (!isset($unique[$key]) || $s->getLastSeenAt() > $unique[$key]->getLastSeenAt()) {
                $unique[$key] = $s;
            }
        }

        $dedupedSessions = array_values($unique);

        // -----------------------------------------
        // BUILD DASHBOARD STATS
        // -----------------------------------------
        $stats = [
            'users' => $this->userRepo->count([]),
            'news' => $this->newsRepo->count([]),
            'services' => $this->servicesRepo->count([]),
            'waitlist' => $this->waitlistRepo->count([]),
            'career_apps_new' => $this->careerRepo->count(['isRead' => false]),
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

            'recent_sessions' => $dedupedSessions,
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Persist the time spent on a page view reported by the client analytics beacon.
     *
     * Locates the latest page view for the current session and URL and updates its
     * duration when provided, returning an empty 204 response either way.
     */
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

    /**
     * Return the count of page views recorded in the last minute as JSON.
     */
    #[Route('/admin/live-traffic')]
    public function liveTraffic(AnalyticsPageViewRepository $repo): JsonResponse
    {
        return new JsonResponse([
            'views' => $repo->countLastMinuteViews(),
        ]);
    }
}
