<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 19.05.2018 20:39
 */

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    public function member(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/member.html.twig', ['settings' => $selectSettings]);
    }

    public function settings(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/settings.html.twig', ['settings' => $selectSettings]);
    }
}
