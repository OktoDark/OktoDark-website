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

use App\Entity\AnalyticsPageView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticsPageViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsPageView::class);
    }

    public function findLatestBySessionAndUrl(string $sessionId, string $url): ?AnalyticsPageView
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.sessionId = :sid')
            ->andWhere('v.url = :url')
            ->setParameter('sid', $sessionId)
            ->setParameter('url', $url)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countToday(): int
    {
        $today = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function topRoutes(int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.route AS route, COUNT(v.id) AS total')
            ->groupBy('v.route')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function trafficLastDays(int $days = 7): array
    {
        $from = (new \DateTimeImmutable())->modify('-'.($days - 1).' days')->setTime(0, 0);

        return $this->createQueryBuilder('v')
            ->select('DATE(v.createdAt) AS date, COUNT(v.id) AS total')
            ->andWhere('v.createdAt >= :from')
            ->setParameter('from', $from)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function hourlyHeatmap(): array
    {
        return $this->createQueryBuilder('v')
            ->select('HOUR(v.createdAt) AS hour, COUNT(v.id) AS total')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function topReferrers(int $limit = 10): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.referrer AS referrer, COUNT(v.id) AS total')
            ->andWhere('v.referrer IS NOT NULL')
            ->andWhere('v.referrer != \'\'')
            ->groupBy('v.referrer')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function countLastMinuteViews(): int
    {
        $qb = $this->createQueryBuilder('v');

        return (int) $qb
            ->select('COUNT(v.id)')
            ->where('v.createdAt > :cutoff')
            ->setParameter('cutoff', new \DateTimeImmutable('-60 seconds'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
