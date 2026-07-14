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

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find activity for specific entity.
     */
    public function findByEntity(string $entityType, int $entityId, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.entityType = :type AND a.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('a.createdAt', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find recent activity.
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}
