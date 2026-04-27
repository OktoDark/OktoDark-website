<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Entity\User;
use App\Repository\BadgeRepository;
use Doctrine\ORM\EntityManagerInterface;

class BadgeService
{
    public function __construct(
        private BadgeRepository $badgeRepo,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Checks and awards badges based on user stats.
     */
    public function checkAutomatedBadges(User $user): void
    {
        $automatedBadges = $this->badgeRepo->findBy(['isPermanent' => false], ['threshold' => 'ASC']);
        $userBadges = $user->getBadges();

        $stats = [
            'posts' => $user->getPostCount(),
            'threads' => $user->getThreadCount(),
            'reputation' => $user->getReputation(),
        ];

        foreach ($automatedBadges as $badge) {
            if (!$badge->getActionType() || null === $badge->getThreshold()) {
                continue;
            }

            if ($userBadges->contains($badge)) {
                continue;
            }

            $currentVal = $stats[$badge->getActionType()] ?? 0;

            if ($currentVal >= $badge->getThreshold()) {
                $user->addBadge($badge);
                // Optionally log or notify user here
            }
        }

        $this->em->flush();
    }

    /**
     * Ensures special role-based permanent badges are assigned.
     */
    public function ensurePermanentBadges(User $user): void
    {
        $roleBadgeMap = [
            'ROLE_ADMIN' => 'role_admin',
            'ROLE_MODERATOR' => 'role_moderator',
        ];

        foreach ($roleBadgeMap as $role => $actionType) {
            if (\in_array($role, $user->getRoles(), true)) {
                $badge = $this->badgeRepo->findOneBy(['actionType' => $actionType, 'isPermanent' => true]);
                if ($badge && !$user->getBadges()->contains($badge)) {
                    $user->addBadge($badge);
                }
            }
        }

        $this->em->flush();
    }
}
