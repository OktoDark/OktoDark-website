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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/forum/social')]
#[IsGranted('ROLE_USER')]
final class ForumSocialController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private string $sender,
    ) {
    }

    #[Route('/follow/{id}', name: 'forum_user_follow', methods: ['POST'])]
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

            // --- Notification Logic for 'follow' ---
            $followerUsername = $currentUser->getUsername();
            $followedUserEmail = $user->getEmail();
            $followedUserLink = $this->urlGenerator->generate('profile_view', ['username' => $user->getUsername()], UrlGeneratorInterface::ABSOLUTE_URL); // Link to followed user's profile

            // On-site notification
            if ($user->getNotificationPreference('follow_onsite')) {
                $notification = new Notification();
                $notification->setUser($user);
                // Re-added 'notification.' prefix
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
                    'followedUser' => $user, // Pass the followed user object
                    'currentUser' => $currentUser, // Pass the current user object
                ]);

                $email = (new Email())
                    ->from($this->sender)
                    ->to($followedUserEmail)
                    ->subject($subject)
                    ->html($body)
                ;
                $email->getHeaders()->addTextHeader('X-Transport', 'no_reply');
                $this->mailer->send($email);
            }
            // --- End Notification Logic ---
        }

        $this->em->flush();

        return $this->redirect($this->generateUrl('profile_view', ['username' => $user->getUsername()]));
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
