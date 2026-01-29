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

use App\Form\ChangePasswordType;
use App\Form\UserType;
use App\Repository\OurGamesRepository;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/member')]
final class MemberController extends AbstractController
{
    #[Route('/', methods: ['GET'], name: 'member_area')]
    #[Cache(smaxage: 10)]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function member(SettingsRepository $settings, OurGamesRepository $ourGames): Response
    {
        return $this->render('@theme/member/member.html.twig', [
            'settings' => $settings->findAll(),
            'games' => $ourGames->findAll(),
        ]);
    }

    #[Route('/profile', methods: ['GET', 'POST'], name: 'profile_area')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function editProfile(EntityManagerInterface $em, SettingsRepository $settings, Request $request): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'user.updated_successfully');

            return $this->redirectToRoute('profile_area');
        }

        return $this->render('@theme/member/profile.html.twig', [
            'user' => $user,
            'form' => $form,
            'settings' => $settings->findAll(),
        ]);
    }

    #[Route('/settings', methods: ['GET'], name: 'settings_area')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function settings(SettingsRepository $settings): Response
    {
        return $this->render('@theme/member/settings.html.twig', [
            'settings' => $settings->findAll(),
        ]);
    }

    #[Route('/play_online', methods: ['GET'], name: 'play_online')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function memberGames(SettingsRepository $settings, OurGamesRepository $ourGames): Response
    {
        $games = $ourGames->findAll();

        return $this->render('@theme/member/play_online.html.twig', [
            'settings' => $settings->findAll(),
            'games' => $games,
            'playonline' => $games,
        ]);
    }

    #[Route('/viewPage', methods: ['GET'], name: 'viewPage_area')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function viewPage(SettingsRepository $settings): Response
    {
        return $this->render('@theme/member/member.html.twig', [
            'settings' => $settings->findAll(),
        ]);
    }

    #[Route('/edit', methods: ['GET', 'POST'], name: 'user_edit')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
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
    public function changePassword(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $hasher->hashPassword($user, $form->get('newPassword')->getData())
            );
            $em->flush();

            return $this->redirectToRoute('security_logout');
        }

        return $this->render('member/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}
