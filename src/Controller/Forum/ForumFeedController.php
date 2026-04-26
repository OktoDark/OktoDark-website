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

use App\Entity\User;
use App\Repository\ForumPostRepository;
use App\Repository\ForumThreadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forum/feed')]
#[IsGranted('ROLE_USER')]
final class ForumFeedController extends AbstractController
{
    #[Route('', name: 'forum_feed', methods: ['GET'])]
    public function index(ForumThreadRepository $threadRepo, ForumPostRepository $postRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // 1. New threads from followed users
        $followingIds = $user->getFollowing()->map(static fn ($u) => $u->getId())->toArray();
        $followedThreads = [];
        if (!empty($followingIds)) {
            $followedThreads = $threadRepo->createQueryBuilder('t')
                ->where('t.author IN (:ids)')
                ->setParameter('ids', $followingIds)
                ->orderBy('t.createdAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        }

        // 2. New replies in subscribed threads
        $subscribedIds = $user->getSubscribedThreads()->map(static fn ($t) => $t->getId())->toArray();
        $subscribedReplies = [];
        if (!empty($subscribedIds)) {
            $subscribedReplies = $postRepo->createQueryBuilder('p')
                ->where('p.thread IN (:tids)')
                ->andWhere('p.author != :currentUser')
                ->setParameter('tids', $subscribedIds)
                ->setParameter('currentUser', $user)
                ->orderBy('p.createdAt', 'DESC')
                ->setMaxResults(15)
                ->getQuery()
                ->getResult();
        }

        return $this->render('@theme/forum/feed.html.twig', [
            'followed_threads' => $followedThreads,
            'subscribed_replies' => $subscribedReplies,
        ]);
    }
}
