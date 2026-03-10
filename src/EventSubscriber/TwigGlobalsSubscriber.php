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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class TwigGlobalsSubscriber implements EventSubscriberInterface
{
    public function __construct(private Environment $twig)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Ignore sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $this->twig->addGlobal('currentLocale', $request->getLocale());
        $this->twig->addGlobal('currentRoute', $request->attributes->get('_route'));
        $this->twig->addGlobal('currentParams', $request->attributes->get('_route_params', []));
    }
}
