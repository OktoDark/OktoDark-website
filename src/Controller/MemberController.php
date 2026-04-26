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

use App\Entity\TrustedDevice;
use App\Entity\User;
use App\Form\AvatarUploadType;
use App\Form\ChangePasswordType;
use App\Form\ForumProfileSettingsType;
use App\Form\PreferencesType;
use App\Form\ProfileType;
use App\Repository\AccountActivityRepository;
use App\Repository\OurGamesRepository;
use App\Service\SocialLinkParser;
use App\Service\TrustedDeviceService;
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
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function member(OurGamesRepository $ourGames): Response
    {
        return $this->render('@theme/member/member.html.twig', [
            'games' => $ourGames->findAll(),
        ]);
    }

    #[Route('/play_online', name: 'play_online', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function memberGames(OurGamesRepository $ourGames): Response
    {
        $games = $ourGames->findAll();

        return $this->render('@theme/member/play_online.html.twig', [
            'games' => $games,
            'playonline' => $games,
        ]);
    }

    #[Route('/settings', name: 'settings_area', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function settings(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        SocialLinkParser $parser,
        AccountActivityRepository $activityRepo,
        TrustedDeviceService $trustedDeviceService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $normalized = [];

        foreach ($user->getSocialLinks() as $key => $value) {
            if (\is_int($key) && \is_string($value)) {
                $detected = $parser->detectAndBuild($value);
                $username = mb_ltrim(parse_url($detected['url'], \PHP_URL_PATH), '/@');

                $normalized[] = [
                    'network' => $detected['network'],
                    'username' => $username,
                ];
                continue;
            }

            if (\is_string($key) && \is_string($value)) {
                $username = mb_ltrim(parse_url($value, \PHP_URL_PATH), '/@');

                $normalized[] = [
                    'network' => $key,
                    'username' => $username,
                ];
                continue;
            }

            if (\is_array($value) && isset($value['network'], $value['username'])) {
                $normalized[] = $value;
            }
        }

        $user->setSocialLinks($normalized);

        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $rawLinks = $profileForm->get('socialLinks')->getData();
            $clean = [];

            foreach ($rawLinks as $entry) {
                $network = $entry['network'] ?? null;
                $username = mb_ltrim($entry['username'] ?? '', '@/');

                if (!$network || !$username) {
                    continue;
                }

                $clean[] = [
                    'network' => $network,
                    'username' => $username,
                ];
            }

            $user->setSocialLinks($clean);

            $em->flush();
            $this->addFlash('success', 'Your profile was updated.');

            return $this->redirectToRoute('settings_area', [
                'tab' => 'profile',
            ]);
        }

        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $user->setPassword(
                $hasher->hashPassword($user, $passwordForm->get('newPassword')->getData())
            );

            $em->flush();
            $this->addFlash('success', 'Password changed successfully.');

            return $this->redirectToRoute('settings_area', [
                'tab' => 'security',
            ]);
        }

        $preferencesForm = $this->createForm(PreferencesType::class, $user);
        $preferencesForm->handleRequest($request);

        if ($preferencesForm->isSubmitted() && $preferencesForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Preferences saved.');

            return $this->redirectToRoute('settings_area', [
                'tab' => 'preferences',
            ]);
        }

        $forumForm = $this->createForm(ForumProfileSettingsType::class, $user);
        $forumForm->handleRequest($request);

        if ($forumForm->isSubmitted() && $forumForm->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Forum settings updated.');

            return $this->redirectToRoute('settings_area', [
                'tab' => 'forum',
            ]);
        }

        if ($request->isMethod('POST') && 'security_settings' === $request->request->get('form_id')) {
            $user->setLoginAlertsEnabled($request->request->has('login_alerts_enabled'));
            $user->setTrustedDevicesEnabled($request->request->has('trusted_devices_enabled'));
            $user->setTwofaResendEnabled($request->request->has('twofa_resend_enabled'));

            $em->flush();

            $this->addFlash('success', 'Security settings updated.');

            return $this->redirectToRoute('settings_area', [
                'tab' => 'security',
            ]);
        }

        $avatarForm = $this->createForm(AvatarUploadType::class);
        $avatarForm->handleRequest($request);

        if ($avatarForm->isSubmitted() && $avatarForm->isValid()) {
            $file = $avatarForm->get('avatar')->getData();

            if ($file) {
                $newFilename = uniqid().'.'.$file->guessExtension();

                try {
                    $file->move($this->getParameter('avatars_directory'), $newFilename);
                } catch (FileException) {
                    $this->addFlash('error', 'Upload failed.');
                }

                $user->setAvatar($newFilename);
                $em->flush();

                $this->addFlash('success', 'Avatar updated.');

                return $this->redirectToRoute('settings_area', [
                    'tab' => 'profile',
                ]);
            }
        }

        $activities = $activityRepo->findRecentForUser($user);
        $currentFingerprint = $trustedDeviceService->getCurrentFingerprint($request);

        return $this->render('@theme/member/settings.html.twig', [
            'profileForm' => $profileForm->createView(),
            'passwordForm' => $passwordForm->createView(),
            'preferencesForm' => $preferencesForm->createView(),
            'avatarForm' => $avatarForm->createView(),
            'forumForm' => $forumForm->createView(),
            'user' => $user,
            'activities' => $activities,
            'currentFingerprint' => $currentFingerprint,
        ]);
    }

    #[Route('/settings/activity/clear', name: 'settings_activity_clear', methods: ['POST'])]
    public function clearActivity(
        AccountActivityRepository $repo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $repo->deleteAllForUser($user);

        $this->addFlash('success', 'Your recent activity has been cleared.');

        return $this->redirectToRoute('settings_area', ['tab' => 'security']);
    }

    #[Route('/settings/trusted-devices/delete/{id}', name: 'trusted_device_delete')]
    public function deleteDevice(
        TrustedDevice $device,
        TrustedDeviceService $service,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($device->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $service->removeTrustedDevice($device);

        $this->addFlash('success', 'Device removed.');

        return $this->redirectToRoute('settings_area', ['tab' => 'security']);
    }

    #[Route('/settings/devices/remove/{id}', name: 'settings_devices_remove')]
    public function removeDevice(
        TrustedDevice $device,
        TrustedDeviceService $service,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($device->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $service->removeTrustedDevice($device);

        $this->addFlash('success', 'Trusted device removed.');

        return $this->redirectToRoute('settings_area', ['tab' => 'security']);
    }
}
