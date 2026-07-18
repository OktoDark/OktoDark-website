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

use App\Security\Attribute\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SeasonsController extends AbstractController
{
    #[Route('/tracker/seasons', name: 'app_tracker_seasons')]
    #[Permission('tracker.season.view')]
    public function index(): Response
    {
        return $this->render('@theme/tracker/seasons/index.html.twig');
    }
}
