<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Forum;

use App\Entity\ForumThread;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forum/social')]
#[IsGranted('ROLE_USER')]
final class ForumSocialController extends AbstractController
{
    #[Route('/follow/{id}', name: 'forum_user_follow', methods: ['POST'])]
    public function followUser(User $user, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($user === $currentUser) {
            $this->addFlash('error', 'You cannot follow yourself.');

            return $this->redirectToRoute('admin_user_view', ['id' => $user->getId()]); // Or profile view
        }

        if ($currentUser->getFollowing()->contains($user)) {
            $currentUser->unfollow($user);
            $this->addFlash('success', 'You unfollowed '.$user->getUsername());
        } else {
            $currentUser->follow($user);
            $this->addFlash('success', 'You are now following '.$user->getUsername());
        }

        $em->flush();

        return $this->redirect($this->generateUrl('admin_user_view', ['id' => $user->getId()])); // Placeholder
    }

    #[Route('/subscribe/{id}', name: 'forum_thread_subscribe', methods: ['POST'])]
    public function subscribeThread(ForumThread $thread, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser->getSubscribedThreads()->contains($thread)) {
            $currentUser->unsubscribeFromThread($thread);
            $this->addFlash('success', 'Unsubscribed from thread.');
        } else {
            $currentUser->subscribeToThread($thread);
            $this->addFlash('success', 'Subscribed to thread updates.');
        }

        $em->flush();

        return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
    }
}
