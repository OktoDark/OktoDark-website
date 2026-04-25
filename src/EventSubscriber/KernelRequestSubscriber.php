<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment as TwigEnvironment;

readonly class KernelRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TwigEnvironment $twig,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                // Priority must be lower than 8 (Firewall) so that the user and roles are available
                ['onMaintenance', 5],
            ],
        ];
    }

    public function onMaintenance(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // 1. CHECK IF MAINTENANCE MODE IS ON
        $mode = $_ENV['MAINTENANCE_MODE'] ?? $_SERVER['MAINTENANCE_MODE'] ?? '0';
        if (!filter_var($mode, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // 2. ALWAYS ALLOW login, logout, and debug tools so admins can authenticate
        if (preg_match('/^\/([a-z]{2}\/)?(login|logout|_profiler|_wdt)/', $path)) {
            return;
        }

        // 3. ALLOW ADMINS to bypass maintenance mode
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // 4. EVERYONE ELSE (Guests/Users) -> SHOW MAINTENANCE PAGE
        try {
            $content = $this->twig->render('maintenance/index.html.twig');
        } catch (\Exception) {
            $content = '<h1>Maintenance</h1><p>We will be back soon.</p>';
        }

        $event->setResponse(new Response($content, Response::HTTP_SERVICE_UNAVAILABLE));
        $event->stopPropagation();
    }
}
