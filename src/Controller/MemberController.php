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

use App\Form\AvatarUploadType;
use App\Form\ChangePasswordType;
use App\Form\PreferencesType;
use App\Form\ProfileType;
use App\Form\UserType;
use App\Repository\OurGamesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/member')]
final class MemberController extends AbstractController
{
    #[Route('/', name: 'member_area', methods: ['GET'])]
    #[Cache(smaxage: 10)]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function member(OurGamesRepository $ourGames): Response
    {
        return $this->render('@theme/member/member.html.twig', [
            'games' => $ourGames->findAll(),
        ]);
    }

    #[Route('/profile', name: 'profile_area', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function editProfile(EntityManagerInterface $em, Request $request): Response
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
            'users' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/play_online', name: 'play_online', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function memberGames(OurGamesRepository $ourGames): Response
    {
        $games = $ourGames->findAll();

        return $this->render('@theme/member/play_online.html.twig', [
            'games' => $games,
            'playonline' => $games,
        ]);
    }

    #[Route('/settings', name: 'settings_area', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function settings(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = $this->getUser();

        // PROFILE FORM
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Your profile was updated.');

            return $this->redirectToRoute('settings_area');
        }

        // PASSWORD FORM
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $user->setPassword(
                $hasher->hashPassword($user, $passwordForm->get('newPassword')->getData())
            );
            $em->flush();
            $this->addFlash('success', 'Password changed successfully.');

            return $this->redirectToRoute('settings_area');
        }

        // PREFERENCES FORM
        $preferencesForm = $this->createForm(PreferencesType::class, $user);
        $preferencesForm->handleRequest($request);

        if ($preferencesForm->isSubmitted() && $preferencesForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Preferences saved.');

            return $this->redirectToRoute('settings_area');
        }

        // AVATAR UPLOAD
        $avatarForm = $this->createForm(AvatarUploadType::class);
        $avatarForm->handleRequest($request);

        if ($avatarForm->isSubmitted() && $avatarForm->isValid()) {
            $file = $avatarForm->get('avatar')->getData();

            if ($file) {
                $newFilename = uniqid().'.'.$file->guessExtension();

                try {
                    $file->move($this->getParameter('avatars_directory'), $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Upload failed.');
                }

                $user->setAvatar($newFilename);
                $em->flush();

                $this->addFlash('success', 'Avatar updated.');

                return $this->redirectToRoute('settings_area');
            }
        }

        return $this->render('@theme/member/settings.html.twig', [
            'profileForm' => $profileForm->createView(),
            'passwordForm' => $passwordForm->createView(),
            'preferencesForm' => $preferencesForm->createView(),
            'avatarForm' => $avatarForm->createView(),
            'user' => $user,
        ]);
    }
}
