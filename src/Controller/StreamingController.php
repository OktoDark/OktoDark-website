<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StreamingController extends AbstractController
{
    #[Route('/streaming', name: 'streaming', methods: ['GET'])]
    public function streaming(): Response
    {
        return $this->render('@theme/streaming.html.twig');
    }
}
