<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Repository;

use App\Entity\ForumPost;
use App\Entity\ForumReaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ForumReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumReaction::class);
    }

    public function countUpvotes(ForumPost $post): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.post = :post')
            ->andWhere('r.type = :type')
            ->setParameter('post', $post)
            ->setParameter('type', 'upvote')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDownvotes(ForumPost $post): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.post = :post')
            ->andWhere('r.type = :type')
            ->setParameter('post', $post)
            ->setParameter('type', 'downvote')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
