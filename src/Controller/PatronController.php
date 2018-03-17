<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 15.03.2018 20:19
 */

namespace App\Controller;

use Patreon\Patreon;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class PatronController extends Controller
{
    public function patreon()
    {
        return $this->render(theme_site.'/patron.html.twig', array(
        ));
    }
}
