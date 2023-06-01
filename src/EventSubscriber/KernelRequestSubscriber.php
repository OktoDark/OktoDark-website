<?php

declare(strict_types=1);

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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment as TwigEnvironment;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    private TwigEnvironment $twig;

    public function __construct(TwigEnvironment $twig)
    {
        $this->twig = $twig;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onMaintenance', \PHP_INT_MAX - 1000],
            ],
        ];
    }

    public function onMaintenance(RequestEvent $event): void
    {
        /** @var bool $isMaintenance */
        $isMaintenance = filter_var($_ENV['MAINTENANCE_MODE'] ?? '0', \FILTER_VALIDATE_BOOLEAN);
        $isCli = \PHP_SAPI === 'cli';

        if ($isMaintenance && !$isCli) {
            $event->setResponse(new Response(
                $this->twig->render('@theme/maintenance/maintenance.html.twig'),
                Response::HTTP_SERVICE_UNAVAILABLE,
            ));
            $event->stopPropagation();
        }
    }
}
