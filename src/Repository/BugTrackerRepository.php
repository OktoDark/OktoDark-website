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

use App\Entity\BugTracker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class BugTrackerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BugTracker::class);
    }

    public function createListQueryBuilder(?string $search = null, ?bool $activeOnly = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('bt')
            ->leftJoin('bt.owner', 'owner')
            ->addSelect('owner')
            ->leftJoin('bt.ourGame', 'game')
            ->addSelect('game')
            ->leftJoin('bt.trackers', 'trackers')
            ->addSelect('trackers');

        if ($search) {
            $qb->andWhere($qb->expr()->orX('bt.name LIKE :search', 'bt.slug LIKE :search'))
                ->setParameter('search', '%'.$search.'%');
        }

        if (null !== $activeOnly) {
            $qb->andWhere('bt.isActive = :active')
                ->setParameter('active', $activeOnly);
        }

        return $qb->orderBy('bt.createdAt', 'DESC');
    }

    /**
     * Returns paginated results for the admin bug tracker list.
     *
     * @return array{0: BugTracker[], 1: int}
     */
    public function findPaginated(?string $search = null, ?bool $activeOnly = null, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createListQueryBuilder($search, $activeOnly);

        $total = (int) \count($qb->getQuery()->getResult());

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return [$qb->getQuery()->getResult(), $total];
    }

    public function findActiveBySlug(string $slug): ?BugTracker
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }

    public function findOneBySlug(string $slug): ?BugTracker
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
