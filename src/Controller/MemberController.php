<?php
/**
 * Copyright (c) 2019 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 16.03.2019 17:30
 */

namespace App\Controller;

use App\Repository\OurGamesRepository;
use App\Repository\SettingsRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/member")
 * @IsGranted("IS_AUTHENTICATED_FULLY")
 */
class MemberController extends AbstractController
{
    /**
     * @Route("/member", methods={"GET"}, name="member_area")
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
