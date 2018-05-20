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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MemberController extends AbstractController
{
    /**
     * @Route("/member", methods={"GET"}, name="member")
     * @param Connection $connection
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function member(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/member.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/settings", methods={"GET"}, name="settings")
     * @param Connection $connection
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function settings(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/settings.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/viewPage", methods={"GET"}, name="viewPage")
     * @param Connection $connection
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewPage(Connection $connection): Response
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/bepatron.html.twig', ['settings' => $selectSettings]);
    }
}
