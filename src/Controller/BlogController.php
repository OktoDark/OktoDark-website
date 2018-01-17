<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 17.01.2018 03:13
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BlogController extends Controller
{
    public function blog()
    {
        return $this->render("main/blog.html.twig", array(

        ));
    }
}
