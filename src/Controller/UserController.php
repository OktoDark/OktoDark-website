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

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\UserType;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * Controller used to manage current user.
 */
#[Route('/profile'), IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserController extends AbstractController
{
    #[Route('/edit', methods: ['GET', 'POST'], name: 'user_edit')]
    public function edit(
        #[CurrentUser] User $user,
        SettingsRepository $settings,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'user.updated_successfully');

            return $this->redirectToRoute('user_edit');
        }

        return $this->render('@theme/member/edit.html.twig', [
            'user' => $user,
            'form' => $form,
            'settings' => $settings->findAll(),
        ]);
    }

    #[Route('/change-password', methods: ['GET', 'POST'], name: 'user_change_password')]
    public function changePassword(
        #[CurrentUser] User $user,
        SettingsRepository $settings,
        Request $request,
        EntityManagerInterface $entityManager,
        LogoutUrlGenerator $logoutUrlGenerator,
    ): Response {
        $selectSettings = $settings->findAll();

        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('security_logout');
        }

        return $this->render('@theme/member/change_password.html.twig', [
            'form' => $form,
            'settings' => $settings->findAll(),
        ]);
    }
}
