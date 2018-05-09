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

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MaintenanceListener extends Controller
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $maintenance = $this->container->hasParameter('maintenance') ? $this->container->getParameter('maintenance') : false;
        $debug = in_array($this->container->get('kernel')->getEnvironment(), ['prod']);

        if ($maintenance && !$debug)
        {
            $engine = $this->container->get('app.maintenance');
            $template = $engine->renderView('@theme/maintenance/maintenance.html.twig',[]);
            $event->setResponse(new Response($template, 503));
            $event->stopPropagation();
        }
    }
}