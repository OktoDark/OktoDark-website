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
use App\Entity\Notification;
use App\Entity\User;
use App\Security\Attribute\Permission;
use App\Service\EmailIdentityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/forum/social')]
#[Permission('forum.use.social', group: 'Forum', label: 'Use social features')]
final class ForumSocialController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private EmailIdentityService $emailIdentity,
    ) {
    }

    #[Route('/follow/{id}', name: 'forum_user_follow', methods: ['POST'])]
    #[Permission('forum.social.follow', group: 'Forum', label: 'Follow forum users')]
    public function followUser(User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($user === $currentUser) {
            $this->addFlash('error', 'You cannot follow yourself.');

            return $this->redirectToRoute('profile_view', ['username' => $user->getUsername()]);
        }

        if ($currentUser->getFollowing()->contains($user)) {
            $currentUser->unfollow($user);
            $this->addFlash('success', 'You unfollowed '.$user->getUsername());
        } else {
            $currentUser->follow($user);
            $this->addFlash('success', 'You are now following '.$user->getUsername());

            $followerUsername = $currentUser->getUsername();
            $followedUserEmail = $user->getEmail();
            $followedUserLink = $this->urlGenerator->generate(
                'profile_view',
                ['username' => $user->getUsername()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // On-site notification
            if ($user->getNotificationPreference('follow_onsite')) {
                $notification = new Notification();
                $notification->setUser($user);
                $notification->setTitle($this->translator->trans('notification.new_follower.title', [], 'notifications'));
                $notification->setMessage($this->translator->trans('notification.new_follower.message', ['%username%' => $followerUsername], 'notifications'));
                $notification->setLink($followedUserLink);
                $this->em->persist($notification);
            }

            // Email notification
            if ($user->getNotificationPreference('follow_email')) {
                $subject = $this->translator->trans('notification.new_follower.email_subject', [], 'notifications');
                $body = $this->renderView('emails/notifications/new_follower.html.twig', [
                    'followerUsername' => $followerUsername,
                    'followedUserLink' => $followedUserLink,
                    'followedUser' => $user,
                    'currentUser' => $currentUser,
                ]);

                $email = (new Email())
                    ->from($this->emailIdentity->noreply())
                    ->to($followedUserEmail)
                    ->subject($subject)
                    ->html($body);

                $email->getHeaders()->addTextHeader('X-Transport', 'no_reply');
                $this->mailer->send($email);
            }
        }

        $this->em->flush();

        return $this->redirectToRoute('profile_view', ['username' => $user->getUsername()]);
    }

    #[Route('/subscribe/{id}', name: 'forum_thread_subscribe', methods: ['POST'])]
    #[Permission('forum.social.subscribe', group: 'Forum', label: 'Subscribe to forum threads')]
    public function subscribeThread(ForumThread $thread): Response
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

        $this->em->flush();

        return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
    }
}
