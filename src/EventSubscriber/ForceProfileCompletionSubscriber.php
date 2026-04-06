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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForceProfileCompletionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        $user = $this->security->getUser();

        if (!$user) {
            return;
        }

        // Allow these routes even if profile is incomplete
        $allowedRoutes = [
            'app_complete_profile',
            'app_logout',
            'app_verify_email',
            'app_register',
            'app_register_check_email',
            'app_register_disabled',
            'check_username',
            'check_email',
        ];

        if (\in_array($route, $allowedRoutes, true)) {
            return;
        }

        // If user is not active, force profile completion
        if (!$user->isActive()) {
            $event->setResponse(
                new RedirectResponse(
                    $this->urlGenerator->generate('app_complete_profile')
                )
            );
        }
    }
}
