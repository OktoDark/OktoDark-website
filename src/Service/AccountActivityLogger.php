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

use App\Entity\AccountActivity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AccountActivityLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {
    }

    public function log(User $user, string $type, ?array $meta = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $activity = new AccountActivity();
        $activity->setUser($user)
            ->setType($type)
            ->setMeta($meta ?? []);

        if ($request) {
            $activity->setIp($request->getClientIp());
            $activity->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->em->persist($activity);
        $this->em->flush();
    }
}
