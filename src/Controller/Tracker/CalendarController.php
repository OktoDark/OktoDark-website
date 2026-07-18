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

class CalendarController extends AbstractController
{
    #[Route('/tracker/calendar', name: 'app_tracker_calendar')]
    #[Permission('tracker.view.calendar')]
    public function index(): Response
    {
        return $this->render('@theme/tracker/calendar/index.html.twig');
    }
}
