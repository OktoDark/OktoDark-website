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

use App\Entity\ForumPost;
use App\Entity\ForumThread;
use App\Entity\User;
use App\Service\NotificationService;

class ForumNotificationService
{
    public function __construct(private NotificationService $notifier)
    {
    }

    public function notifyNewReply(ForumPost $post): void
    {
        $thread = $post->getThread();
        $author = $thread->getAuthor();
        $replier = $post->getAuthor();

        if ($author === $replier) {
            return;
        }

        $this->notifier->notify(
            $author,
            'New Forum Reply',
            sprintf('%s replied to your thread "%s"', $replier->getUsername(), $thread->getTitle()),
            '/forum/thread/view/' . $thread->getSlug() . '#post-' . $post->getId()
        );
    }

    public function notifyMention(User $user, ForumPost $post): void
    {
        if ($user === $post->getAuthor()) {
            return;
        }

        $this->notifier->notify(
            $user,
            'You were mentioned',
            sprintf('%s mentioned you in a forum post', $post->getAuthor()->getUsername()),
            '/forum/thread/view/' . $post->getThread()->getSlug() . '#post-' . $post->getId()
        );
    }

    public function notifyThreadStatusChange(ForumThread $thread, string $action): void
    {
        $this->notifier->notify(
            $thread->getAuthor(),
            'Thread Status Updated',
            sprintf('Your thread "%s" has been %s', $thread->getTitle(), $action),
            '/forum/thread/view/' . $thread->getSlug()
        );
    }
}
