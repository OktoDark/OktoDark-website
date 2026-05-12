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

use App\Entity\BoardColumn;
use App\Entity\Card;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    /**
     * Find cards by column.
     */
    public function findByColumn(BoardColumn $column): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.column = :column')
            ->setParameter('column', $column)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find cards assigned to user.
     */
    public function findByAssignee(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.assignees', 'ca')
            ->where('ca.assignee = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Get next position for a new card in column.
     */
    public function getNextPosition(BoardColumn $column): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('MAX(c.position) as max_pos')
            ->where('c.column = :column')
            ->setParameter('column', $column)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return ($result ?? 0) + 1;
    }

    /**
     * Find card with all details.
     */
    public function findCardWithDetails(int $id): ?Card
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.assignees', 'ca')
            ->leftJoin('ca.assignee', 'u')
            ->leftJoin('c.labels', 'l')
            ->leftJoin('c.comments', 'cc')
            ->leftJoin('cc.author', 'ca2')
            ->leftJoin('c.bugs', 'b')
            ->addSelect('ca', 'u', 'l', 'cc', 'ca2', 'b')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find card with all details including bugs collection.
     */
    public function findWithBugs(int $id): ?Card
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.assignees', 'ca')
            ->leftJoin('ca.assignee', 'u')
            ->leftJoin('c.labels', 'l')
            ->leftJoin('c.comments', 'cc')
            ->leftJoin('cc.author', 'ca2')
            ->leftJoin('c.bugs', 'b')
            ->addSelect('ca', 'u', 'l', 'cc', 'ca2', 'b')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find cards due soon.
     */
    public function findUpcomingDue(int $days = 7): array
    {
        $now = new \DateTime();
        $future = (clone $now)->modify("+$days days");

        return $this->createQueryBuilder('c')
            ->where('c.dueDate BETWEEN :now AND :future')
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->orderBy('c.dueDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
