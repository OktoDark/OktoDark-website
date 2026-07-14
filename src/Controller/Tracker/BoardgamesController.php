<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Tracker;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BoardgamesController extends AbstractController
{
    #[Route('/tracker/boardgames', name: 'app_tracker_boardgames')]
    public function index(): Response
    {
        return $this->render('@theme/tracker/boardgames/index.html.twig');
    }
}
