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

use App\Repository\SettingsRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/member")
 *
 * Class MemberController
 * @package App\Controller
 */
class MemberController extends AbstractController
{
    /**
     * @Route("/", methods={"GET","POST"}, name="member_area")
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function member(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/member/member.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/settings", methods={"GET"}, name="settings_area")
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function settings(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/member/settings.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/viewPage", methods={"GET"}, name="viewPage_area")
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewPage(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/member/member.html.twig', ['settings' => $selectSettings]);
    }
}
