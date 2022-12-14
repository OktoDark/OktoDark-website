<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller;

use App\Form\Type\ChangePasswordType;
use App\Form\UserType;
use App\Repository\OurGamesRepository;
use App\Repository\SettingsRepository;
use App\Repository\UserRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

#[Route('/member')]
class MemberController extends AbstractController
{
    #[Route('/', methods: ['GET'], name: 'member_area')]
    #[Cache(smaxage: 10)]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function member(SettingsRepository $settings, OurGamesRepository $ourGames): Response
    {
        $selectSettings = $settings->findAll();
        $AllGames = $ourGames->findAll();

        return $this->render('@theme/member/member.html.twig', [
            'settings' => $selectSettings,
            'games' => $AllGames,
        ]);
    }

    #[Route('/profile', methods: ['GET', 'POST'], name: 'profile_area')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit_profile(SettingsRepository $settings, UserRepository $user, Request $request): Response
    {
        $selectSettings = $settings->findAll();
        $user = $user->findAll();

        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', 'user.updated_successfully');

            return $this->redirectToRoute('edit_profile');
        }

        return $this->render('@theme/member/profile.html.twig', [
            'user' => $user,
            'form' => $form,
            'settings' => $selectSettings,
        ]);
    }

    #[Route('/settings', methods: ['GET'], name: 'settings_area')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function settings(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/member/settings.html.twig', ['settings' => $selectSettings]);
    }

    #[Route('/play_online', methods: ['GET'], name: 'play_online')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function member_games(SettingsRepository $settings, OurGamesRepository $ourGames): Response
    {
        $selectSettings = $settings->findAll();
        $AllGames = $ourGames->findAll();
        $PlayOnline = $ourGames->findAll();

        return $this->render('@theme/member/play_online.html.twig', [
            'settings' => $selectSettings,
            'games' => $AllGames,
            'playonline' => $PlayOnline,
        ]);
    }

    #[Route('/viewPage', methods: ['GET'], name: 'viewPage_area')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function viewPage(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/member/member.html.twig', ['settings' => $selectSettings]);
    }

    #[Route('/edit', methods: ['GET', 'POST'], name: 'user_edit')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', 'user.updated_successfully');

            return $this->redirectToRoute('user_edit');
        }

        return $this->render('member/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/change-password', methods: ['GET', 'POST'], name: 'user_change_password')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(Request $request, UserPasswordEncoderInterface $encoder): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($encoder->encodePassword($user, $form->get('newPassword')->getData()));

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('security_logout');
        }

        return $this->render('member/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}
