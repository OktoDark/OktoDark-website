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

use App\Entity\ForumPost;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ReputationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ForumPostSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ReputationService $reputationService,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ForumPost) {
            return;
        }

        // Award reputation
        $this->reputationService->awardPoints($entity->getAuthor(), ReputationService::POINTS_PER_POST);

        // Detect mentions and send notifications based on user preferences
        $this->handleMentions($entity);
    }

    private function handleMentions(ForumPost $post): void
    {
        $content = $post->getContent();
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);

        if (!empty($matches[1])) {
            $usernames = array_unique($matches[1]);

            foreach ($usernames as $username) {
                /** @var User|null $user */
                $user = $this->userRepository->findOneBy(['username' => $username]);

                // Only send notification if user exists, is not the author, and has forum_onsite notifications enabled
                if ($user && $user !== $post->getAuthor() && $user->getNotificationPreference('forum_onsite')) {
                    $notification = new Notification();
                    $notification->setUser($user);
                    $notification->setTitle($this->translator->trans('notification.forum_mention.title', [], 'notifications'));
                    $notification->setMessage($this->translator->trans('notification.forum_mention.message', [
                        '%mentioner_username%' => $post->getAuthor()->getUsername(),
                        '%post_title%' => $post->getThread()->getTitle(),
                    ], 'notifications'));
                    $notification->setLink($this->urlGenerator->generate('forum_thread_view', ['slug' => $post->getThread()->getSlug()]));

                    $this->em->persist($notification);
                }
            }
            $this->em->flush();
        }
    }
}
