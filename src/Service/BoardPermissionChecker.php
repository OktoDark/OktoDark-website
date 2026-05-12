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

use App\Entity\Board;
use App\Entity\User;

class BoardPermissionChecker
{
    public function canViewBoard(Board $board, ?User $user): bool
    {
        if ($board->isPublic()) {
            return true;
        }

        if (null === $user) {
            return false;
        }

        return $board->getOwner() === $user || $board->isMember($user);
    }

    public function canEditBoard(Board $board, ?User $user): bool
    {
        if (null === $user) {
            return false;
        }

        return $board->getOwner() === $user || $board->isMember($user);
    }

    public function canDeleteBoard(Board $board, ?User $user): bool
    {
        if (null === $user) {
            return false;
        }

        return $board->getOwner() === $user;
    }

    public function canManageMembers(Board $board, ?User $user): bool
    {
        if (null === $user) {
            return false;
        }

        return $board->getOwner() === $user;
    }

    public function canCreateCard(Board $board, ?User $user): bool
    {
        if (null === $user) {
            return false;
        }

        return $board->getOwner() === $user || $board->isMember($user);
    }

    public function canEditCard(Board $board, ?User $user): bool
    {
        return $this->canCreateCard($board, $user);
    }

    public function canDeleteCard(Board $board, ?User $user): bool
    {
        return $board->getOwner() === $user;
    }
}
