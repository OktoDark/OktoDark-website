<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 17.01.2018 03:12
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class AboutController extends Controller
{
    public function about()
    {
        return $this->render("main/about.html.twig", array(

        ));
    }
}
