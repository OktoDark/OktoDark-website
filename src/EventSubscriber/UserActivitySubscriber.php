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

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $em,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only process main requests, ignore sub-requests (like fragments/renders)
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Fetch a fresh, managed instance of the user
        $managedUser = $this->em->getRepository(User::class)->find($user->getId());

        if ($managedUser) {
            $now = new \DateTime();
            $lastActivity = $managedUser->getLastActivityAt();

            // Only update if no previous activity OR if 1 minute has passed since last update
            // to avoid excessive database writes on every page click.
            if (null === $lastActivity || ($now->getTimestamp() - $lastActivity->getTimestamp() > 60)) {
                $managedUser->setLastActivityAt($now);
                $this->em->flush();
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority must be LOWER than 8 (Security Firewall) to have access to the User token.
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }
}
