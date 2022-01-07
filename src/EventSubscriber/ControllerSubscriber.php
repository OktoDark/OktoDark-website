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

use App\Twig\SourceCodeExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines the method that 'listens' to the 'kernel.controller' event, which is
 * triggered whenever a controller is executed in the application.
 */
class ControllerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SourceCodeExtension $twigExtension
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'registerCurrentController',
        ];
    }

    public function registerCurrentController(ControllerEvent $event): void
    {
        // this check is needed because in Symfony a request can perform any
        // number of sub-requests. See
        // https://symfony.com/doc/current/components/http_kernel/introduction.html#sub-requests
        if ($event->isMainRequest()) {
            $this->twigExtension->setController($event->getController());
        }
    }
}
