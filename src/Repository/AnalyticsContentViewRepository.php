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

use App\Entity\AnalyticsContentView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticsContentViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsContentView::class);
    }

    public function topContent(string $type, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.contentId AS contentId, COUNT(c.id) AS total')
            ->andWhere('c.contentType = :type')
            ->setParameter('type', $type)
            ->groupBy('c.contentId')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }
}
