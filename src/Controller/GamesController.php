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

class GamesController extends Controller
{
    public function games()
    {
        return $this->render('@theme/games.html.twig', array(

        ));
    }
}
