<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Security\Voter;

use App\Entity\Board;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BoardVoter extends Voter
{
    final public const VIEW = 'board_view';
    final public const EDIT = 'board_edit';
    final public const DELETE = 'board_delete';
    final public const MANAGE_MEMBERS = 'board_manage_members';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::MANAGE_MEMBERS], true)
            && $subject instanceof Board;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Board $board */
        $board = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($board, $user),
            self::EDIT => $this->canEdit($board, $user),
            self::DELETE => $this->canDelete($board, $user),
            self::MANAGE_MEMBERS => $this->canManageMembers($board, $user),
            default => false,
        };
    }

    private function canView(Board $board, User $user): bool
    {
        // Admins can view any board
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if ($board->isPublic()) {
            return true;
        }

        return $board->getOwner() === $user || $board->isMember($user);
    }

    private function canEdit(Board $board, User $user): bool
    {
        return $board->getOwner() === $user || $board->isMember($user);
    }

    private function canDelete(Board $board, User $user): bool
    {
        return $board->getOwner() === $user;
    }

    private function canManageMembers(Board $board, User $user): bool
    {
        return $board->getOwner() === $user;
    }
}
