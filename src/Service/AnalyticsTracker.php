<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Entity\AnalyticsPageView;
use App\Entity\AnalyticsSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AnalyticsTracker
{
    public function __construct(
        private RequestStack $requestStack,
        private EntityManagerInterface $em,
        private GeoIpService $geoIp,
    ) {
    }

    public function trackRequest(string $route): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $sessionId = $request->getSession()->getId();
        $user = $request->getUser();

        $ip = $request->headers->get('CF-Connecting-IP')
            ?? $request->headers->get('X-Forwarded-For')
            ?? $request->getClientIp();

        $country = $this->geoIp->getCountryCode($ip);
        $ua = $request->headers->get('User-Agent');
        $platform = $request->headers->get('Sec-CH-UA-Platform');
        $platformVersion = $request->headers->get('Sec-CH-UA-Platform-Version');

        if ($this->isBot($ua)) {
            return;
        }

        $browser = $this->detectBrowser($ua);
        $browserVersion = $this->detectBrowserVersion($ua);
        $os = $platform ?? $this->detectOS($ua);
        $osVersion = $platformVersion ?? $this->detectOSVersion($ua);
        $device = $this->detectDevice($ua);
        $deviceModel = $this->detectDeviceModel($ua);

        $today = (new \DateTimeImmutable('today'));

        // one session per day per sessionId
        $sessionRepo = $this->em->getRepository(AnalyticsSession::class);
        $session = $sessionRepo->createQueryBuilder('s')
            ->where('s.sessionId = :sid')
            ->andWhere('s.createdAt >= :today')
            ->setParameter('sid', $sessionId)
            ->setParameter('today', $today)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$session) {
            $session = new AnalyticsSession();
            $session->setSessionId($sessionId);
            $session->setCreatedAt(new \DateTimeImmutable());
        }

        $session->setUser($user);
        $session->setBrowser($browser);
        $session->setBrowserVersion($browserVersion);
        $session->setOs($os);
        $session->setOsVersion($osVersion);
        $session->setDevice($device);
        $session->setDeviceModel($deviceModel);
        $session->setIp($ip);
        $session->setCountry($country);
        $session->setLastSeenAt(new \DateTimeImmutable());

        $this->em->persist($session);

        $view = new AnalyticsPageView();
        $view->setSessionId($sessionId);
        $view->setUser($user);
        $view->setRoute($route);
        $view->setUrl($request->getUri());
        $view->setReferrer($request->headers->get('referer'));
        $view->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($view);
        $this->em->flush();
    }

    // ---------------------------------------------------------
    // Bot detection
    // ---------------------------------------------------------

    private function isBot(?string $ua): bool
    {
        if (!$ua) {
            return false;
        }

        $ua = mb_strtolower($ua);

        return str_contains($ua, 'bot')
            || str_contains($ua, 'crawl')
            || str_contains($ua, 'spider')
            || str_contains($ua, 'slurp')
            || str_contains($ua, 'ahrefs')
            || str_contains($ua, 'semrush')
            || str_contains($ua, 'bingpreview')
            || str_contains($ua, 'facebookexternalhit')
            || str_contains($ua, 'pingdom')
            || str_contains($ua, 'uptimerobot');
    }

    // ---------------------------------------------------------
    // User-Agent Parsing
    // ---------------------------------------------------------

    private function detectBrowser(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        $ua = mb_strtolower($ua);

        return match (true) {
            str_contains($ua, 'edg') => 'Edge',
            str_contains($ua, 'chrome') && !str_contains($ua, 'edg') => 'Chrome',
            str_contains($ua, 'firefox') => 'Firefox',
            str_contains($ua, 'safari') && !str_contains($ua, 'chrome') => 'Safari',
            str_contains($ua, 'opr') || str_contains($ua, 'opera') => 'Opera',
            default => 'Other',
        };
    }

    private function detectBrowserVersion(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        $patterns = [
            '/Chrome\/([0-9\.]+)/i',
            '/Firefox\/([0-9\.]+)/i',
            '/Version\/([0-9\.]+)/i', // Safari
            '/Edg\/([0-9\.]+)/i',
            '/OPR\/([0-9\.]+)/i',
        ];

        foreach ($patterns as $regex) {
            if (preg_match($regex, $ua, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function detectOS(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        $ua = mb_strtolower($ua);

        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'linux') => 'Linux',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'iOS',
            default => 'Other',
        };
    }

    private function detectOSVersion(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        if (preg_match('/Windows NT ([0-9\.]+)/i', $ua, $m)) {
            return $m[1];
        }

        if (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $m)) {
            return str_replace('_', '.', $m[1]);
        }

        if (preg_match('/Android ([0-9\.]+)/i', $ua, $m)) {
            return $m[1];
        }

        if (preg_match('/OS ([0-9_]+) like Mac OS X/i', $ua, $m)) {
            return str_replace('_', '.', $m[1]); // iOS
        }

        return null;
    }

    private function detectDevice(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        $ua = mb_strtolower($ua);

        return match (true) {
            str_contains($ua, 'mobile') => 'Mobile',
            str_contains($ua, 'tablet') || str_contains($ua, 'ipad') => 'Tablet',
            default => 'Desktop',
        };
    }

    private function detectDeviceModel(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        // iPhone model identifiers
        if (preg_match('/iPhone.*?([0-9]+,?[0-9]*)/i', $ua, $m)) {
            return 'iPhone '.$m[1];
        }

        // iPad model identifiers
        if (preg_match('/iPad.*?([0-9]+,?[0-9]*)/i', $ua, $m)) {
            return 'iPad '.$m[1];
        }

        // Samsung model codes
        if (preg_match('/SM-[A-Z0-9]+/i', $ua, $m)) {
            return 'Samsung '.$m[0];
        }

        // Google Pixel
        if (preg_match('/Pixel [0-9A-Za-z ]+/i', $ua, $m)) {
            return mb_trim($m[0]);
        }

        return null;
    }
}
