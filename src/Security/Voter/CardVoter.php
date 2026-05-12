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

use App\Entity\Card;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CardVoter extends Voter
{
    final public const VIEW = 'card_view';
    final public const EDIT = 'card_edit';
    final public const DELETE = 'card_delete';
    final public const ASSIGN = 'card_assign';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::ASSIGN], true)
            && $subject instanceof Card;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Card $card */
        $card = $subject;
        $board = $card->getBoard();

        if (null === $board) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($board, $user),
            self::EDIT => $this->canEdit($board, $user),
            self::DELETE => $this->canDelete($board, $user),
            self::ASSIGN => $this->canAssign($board, $user),
            default => false,
        };
    }

    private function canView(object $board, User $user): bool
    {
        return $board->getOwner() === $user || $board->isMember($user) || $board->isPublic();
    }

    private function canEdit(object $board, User $user): bool
    {
        return $board->getOwner() === $user || $board->isMember($user);
    }

    private function canDelete(object $board, User $user): bool
    {
        return $board->getOwner() === $user || $board->isMember($user);
    }

    private function canAssign(object $board, User $user): bool
    {
        return $board->getOwner() === $user || $board->isMember($user);
    }
}
