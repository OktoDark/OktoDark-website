<?php
/**
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 13.01.2020, 06:50
 */

namespace App\Controller;

use App\Repository\OurGamesRepository;
use App\Repository\SettingsRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/member")
 * @IsGranted("ROLE_MEMBER")
 */
class MemberController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="member_area")
     * @Cache(smaxage="10")
     */
    public function member(SettingsRepository $settings, OurGamesRepository $ourGames): Response
    {
        $selectSettings = $settings->findAll();
        $AllGames = $ourGames->findAll();

        return $this->render('@theme/member/member.html.twig', [
            'settings' => $selectSettings,
            'games' => $AllGames,
        ]);
    }

    /**
     * @Route("/settings", methods={"GET"}, name="settings_area")
     */
    public function settings(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/member/settings.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/viewPage", methods={"GET"}, name="viewPage_area")
     */
    public function viewPage(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/member/member.html.twig', ['settings' => $selectSettings]);
    }
}
