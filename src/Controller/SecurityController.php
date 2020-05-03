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

use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="security_login")
     */
    public function login(AuthenticationUtils $authenticationUtils, SettingsRepository $settings): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('member_area');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $selectSettings = $settings->findAll();

        return $this->render('@theme/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'settings' => $selectSettings
        ]);
    }

    /**
     * @Route("/logout", name="security_logout")
     */
    public function logout()
    {
        throw new \LogicException('This should never be reached!');
    }
}
