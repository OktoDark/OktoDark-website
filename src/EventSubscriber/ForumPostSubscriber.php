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
use App\Repository\UserRepository;
use App\Service\ReputationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForumPostSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ReputationService $reputationService,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
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

        // Detect mentions
        $this->handleMentions($entity);
    }

    private function handleMentions(ForumPost $post): void
    {
        $content = $post->getContent();
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);

        if (!empty($matches[1])) {
            $usernames = array_unique($matches[1]);

            foreach ($usernames as $username) {
                $user = $this->userRepository->findOneBy(['username' => $username]);
                if ($user && $user !== $post->getAuthor()) {
                    $notification = new Notification();
                    $notification->setUser($user);
                    $notification->setTitle('You were mentioned!');
                    $notification->setMessage(sprintf('%s mentioned you in a forum post.', $post->getAuthor()->getUsername()));
                    $notification->setLink($this->urlGenerator->generate('forum_thread_view', ['slug' => $post->getThread()->getSlug()]));

                    $this->em->persist($notification);
                }
            }
            $this->em->flush();
        }
    }
}
