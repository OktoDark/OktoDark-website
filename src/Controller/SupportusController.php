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

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SupportusController extends AbstractController
{
    /**
     * @Route("/bepatron", methods={"GET"}, name="bepatron")
     */
    public function bepatron(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/bepatron.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/bedonator", methods={"GET"}, name="bedonator")
     */
    public function bedonator(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/bedonator.html.twig', ['settings' => $selectSettings]);
    }
}