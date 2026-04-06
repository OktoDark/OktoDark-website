<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ClientHintsListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        $response->headers->set(
            'Accept-CH',
            'Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version'
        );

        // Optional: request persistent hints
        $response->headers->set(
            'Critical-CH',
            'Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version'
        );
    }
}