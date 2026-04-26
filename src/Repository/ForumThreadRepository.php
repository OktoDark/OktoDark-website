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

use App\Entity\ForumThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumThread>
 */
class ForumThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumThread::class);
    }

    /**
     * @return ForumThread[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.updatedAt', Order::Descending->value)
            ->addOrderBy('t.createdAt', Order::Descending->value)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ForumThread[]
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.author', 'a')
            ->where('t.title LIKE :query OR t.content LIKE :query OR a.username LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('t.updatedAt', Order::Descending->value)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ForumThread[]
     */
    public function findByAuthor(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.author = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', Order::Descending->value)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
