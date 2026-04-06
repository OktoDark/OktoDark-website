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

use App\Entity\AnalyticsSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticsSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsSession::class);
    }

    public function countActiveSessions(int $minutes = 15): int
    {
        $since = new \DateTimeImmutable("-{$minutes} minutes");

        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.lastSeenAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByCountry(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.country AS country, COUNT(s.id) AS total')
            ->andWhere('s.country IS NOT NULL')
            ->groupBy('s.country')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countByBrowser(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.browser AS browser, COUNT(s.id) AS total')
            ->andWhere('s.browser IS NOT NULL')
            ->groupBy('s.browser')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countByDevice(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.device AS device, COUNT(s.id) AS total')
            ->andWhere('s.device IS NOT NULL')
            ->groupBy('s.device')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findTopIps(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.ip AS ip, COUNT(s.id) AS total')
            ->where('s.ip IS NOT NULL')
            ->groupBy('s.ip')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findRecentSessions(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.lastSeenAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
