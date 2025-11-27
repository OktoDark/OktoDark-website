<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Security;

use App\Entity\Post;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PostVoter extends Voter
{
    public const DELETE = 'delete';
    public const EDIT = 'edit';
    public const SHOW = 'show';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // only vote on Post objects and specific attributes
        return $subject instanceof Post
            && \in_array($attribute, [self::SHOW, self::EDIT, self::DELETE], true);
    }

    /**
     * @param Post $post
     */
    protected function voteOnAttribute(string $attribute, mixed $post, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // simple rule: only the author can view/edit/delete their own post
        return $user === $post->getAuthor();
    }
}
