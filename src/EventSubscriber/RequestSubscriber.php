<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class RequestSubscriber implements EventSubscriberInterface
{
    use TargetPathTrait;

    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (
            !$event->isMasterRequest()
            || $request->isXmlHttpRequest()
            || 'security_login' === $request->attributes->get('_route')
        )
        {
            return;
        }

        $this->saveTargetPath($this->session, 'main', $request->getUri());
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest']
        ];
    }
}
