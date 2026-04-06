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

use App\Service\AnalyticsTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AnalyticsSubscriber implements EventSubscriberInterface
{
    public function __construct(private AnalyticsTracker $tracker)
    {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!is_array($controller)) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!$route) {
            return;
        }

        $this->tracker->trackRequest($route);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
