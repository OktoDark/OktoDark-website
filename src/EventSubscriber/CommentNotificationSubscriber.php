<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Entity\User;
use App\Event\CommentCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Notifies post's author about new comments.
 */
final readonly class CommentNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private string $sender,
        private EntityManagerInterface $em,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CommentCreatedEvent::class => 'onCommentCreated',
        ];
    }

    public function onCommentCreated(CommentCreatedEvent $event): void
    {
        $comment = $event->getComment();
        $post = $comment->getPost();
        /** @var User $author */
        $author = $post->getAuthor();

        $linkToPost = $this->urlGenerator->generate('blog_post', [
            'slug' => $post->getSlug(),
            '_fragment' => 'comment_'.$comment->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // --- Email Notification Logic ---
        if ($author->getNotificationPreference('blog_email')) {
            $subject = $this->translator->trans('notification.comment_created.email_subject', [], 'notifications');
            $body = $this->twig->render('emails/notifications/comment_created.html.twig', [
                'postTitle' => $post->getTitle(),
                'linkToPost' => $linkToPost,
                'author' => $author, // The author of the post
                'commentAuthor' => $comment->getAuthor(), // The author of the comment
            ]);

            $email = (new Email())
                ->from($this->sender)
                ->to($author->getEmail())
                ->subject($subject)
                ->html($body)
            ;
            $email->getHeaders()->addTextHeader('X-Transport', 'no_reply');
            $this->mailer->send($email);
        }

        // --- On-site Notification Logic ---
        if ($author->getNotificationPreference('blog_onsite')) {
            $notification = new Notification();
            $notification->setUser($author);
            $notification->setTitle($this->translator->trans('notification.comment_created.onsite_title', [], 'notifications'));
            $notification->setMessage($this->translator->trans('notification.comment_created.onsite_message', [
                '%comment_author%' => $comment->getAuthor()->getUsername(),
                '%post_title%' => $post->getTitle(),
            ], 'notifications'));
            $notification->setLink($linkToPost);

            $this->em->persist($notification);
            $this->em->flush(); // Flush immediately to ensure notification is available
        }
    }
}
