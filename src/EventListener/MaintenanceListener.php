<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 09.05.2018 12:25
 */

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class MaintenanceListener
{
    private $isLocked;
    private $twig;

    public function __construct($isLocked, \Twig_Environment $twig)
    {
        $this->isLocked = $isLocked;
        $this->twig = $twig;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if ( ! $this->isLocked)
        {
            return;
        }

        $page = $this->twig->render('@theme/maintenance/maintenance.html.twig');

        $event->setResponse(
            new Response(
                $page,
                Response::HTTP_SERVICE_UNAVAILABLE
            )
        );
        $event->stopPropagation();
    }
}