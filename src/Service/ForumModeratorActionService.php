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

use App\Entity\ForumModerationLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ForumModeratorActionService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function log(User $moderator, string $action, string $targetType, int $targetId, ?string $details = null): void
    {
        $log = new ForumModerationLog();
        $log->setModerator($moderator);
        $log->setAction($action);
        $log->setTargetType($targetType);
        $log->setTargetId($targetId);
        $log->setDetails($details);

        $this->em->persist($log);
        $this->em->flush();
    }
}
