<?php

/**
 * Copyright (c) 2019 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 16.03.2019 17:30
 */

namespace App\EventSubscriber;

use App\Twig\SourceCodeExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines the method that 'listens' to the 'kernel.controller' event, which is
 * triggered whenever a controller is executed in the application.
 */
class ControllerSubscriber implements EventSubscriberInterface
{
    private $twigExtension;

    public function __construct(SourceCodeExtension $twigExtension)
    {
        $this->twigExtension = $twigExtension;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'registerCurrentController',
        ];
    }

    public function registerCurrentController(FilterControllerEvent $event): void
    {
        // this check is needed because in Symfony a request can perform any
        // number of sub-requests. See
        // https://symfony.com/doc/current/components/http_kernel/introduction.html#sub-requests
        if ($event->isMasterRequest()) {
            $this->twigExtension->setController($event->getController());
        }
    }
}
