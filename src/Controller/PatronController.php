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

use Doctrine\DBAL\Connection;
use Patreon\Patreon;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class PatronController extends Controller
{
    public function patron(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/patron.html.twig', ['settings' => $selectSettings]);
    }
}
