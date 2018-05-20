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
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class CareersController extends Controller
{
    /**
     * @Route("/careers", methods={"GET"}, name="careers")
     * @param Connection $connection
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function careers(Connection $connection)
    {
        $selectSettings = $connection->fetchAll('SELECT * FROM settings');
        $showCareers = $connection->fetchAll('SELECT * FROM careers ORDER BY jobtitle ASC ');

        return $this->render('@theme/careers.html.twig', ['settings' => $selectSettings, 'careers' => $showCareers]);
    }
}
