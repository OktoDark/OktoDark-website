<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 09.05.2018 12:20
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RedirectController extends Controller
{
    public function forum()
    {
        $response = new RedirectResponse('https://forum.oktodark.com');
        $response->send();
    }
}