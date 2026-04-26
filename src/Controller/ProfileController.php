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
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile/{username}', name: 'profile_view', methods: ['GET'])]
    public function viewProfile(
        #[MapEntity(mapping: ['username' => 'username'])] User $user,
    ): Response {
        $viewer = $this->getUser();
        $isOwner = ($viewer && $viewer === $user);

        // Is the profile public?
        $isPublic = $user->getPrivacyValue('profilePublic', true);

        // Should we show the "private profile" message?
        $isPrivateView = (!$isPublic && !$isOwner);

        // Privacy flags — owner always sees everything
        $privacy = [
            'showFirstName' => $isOwner ? true : $user->getPrivacyValue('showFirstName', true),
            'showLastName' => $isOwner ? true : $user->getPrivacyValue('showLastName', true),
            'showEmail' => $isOwner ? true : $user->getPrivacyValue('showEmail', false),
            'showLocation' => $isOwner ? true : $user->getPrivacyValue('showLocation', false),
            'showSocialLinks' => $isOwner ? true : $user->getPrivacyValue('showSocialLinks', true),
            'showRoles' => $isOwner ? true : $user->getPrivacyValue('showRoles', true),
            'showMemberSince' => $isOwner ? true : $user->getPrivacyValue('showMemberSince', true),
            'showAccountStatus' => $isOwner ? true : $user->getPrivacyValue('showAccountStatus', true),
        ];

        return $this->render('@theme/member/profile.html.twig', [
            'users' => $user,
            'viewer' => $viewer,
            'privacy' => $privacy,
            'is_public' => $isPublic,
            'is_privateView' => $isPrivateView,
            'is_owner' => $isOwner,
        ]);
    }
}
