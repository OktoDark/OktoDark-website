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

use App\Entity\ForumPollOption;
use App\Entity\ForumPollVote;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forum/poll')]
#[IsGranted('ROLE_USER')]
final class ForumPollController extends AbstractController
{
    #[Route('/vote/{id}', name: 'forum_poll_vote', methods: ['POST'])]
    public function vote(ForumPollOption $option, EntityManagerInterface $em, Request $request): Response
    {
        $poll = $option->getPoll();
        /** @var User $user */
        $user = $this->getUser();

        if ($poll->hasExpired()) {
            $this->addFlash('error', 'This poll has expired.');

            return $this->redirectToRoute('forum_thread_view', ['slug' => $poll->getThread()->getSlug()]);
        }

        // Check if already voted
        $existingVote = $em->getRepository(ForumPollVote::class)->findOneBy([
            'user' => $user,
            'poll' => $poll,
        ]);

        if ($existingVote) {
            $this->addFlash('error', 'You have already voted in this poll.');

            return $this->redirectToRoute('forum_thread_view', ['slug' => $poll->getThread()->getSlug()]);
        }

        $vote = new ForumPollVote();
        $vote->setUser($user);
        $vote->setPoll($poll);
        $vote->setOption($option);

        $em->persist($vote);
        $em->flush();

        $this->addFlash('success', 'Your vote has been counted.');

        return $this->redirectToRoute('forum_thread_view', ['slug' => $poll->getThread()->getSlug()]);
    }
}
