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

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ReputationService
{
    public const POINTS_PER_POST = 5;
    public const POINTS_PER_THREAD = 10;
    public const POINTS_PER_COMMENT = 2;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function awardPoints(User $user, int $points): void
    {
        $user->addReputation($points);
        $this->em->flush();
    }
}
