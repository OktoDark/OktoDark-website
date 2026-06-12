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

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Finds messages filtered by department.
     */
    public function findByDepartment(string $dept): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.department = :dept')
            ->setParameter('dept', $dept)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchMessages(
        ?string $search,
        string $sortField,
        string $order,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->createQueryBuilder('c');

        if ($search) {
            $qb->andWhere('c.name LIKE :s OR c.email LIKE :s OR c.subject LIKE :s')
                ->setParameter('s', '%'.$search.'%');
        }

        return $qb->orderBy('c.'.$sortField, $order)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countSearch(?string $search): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($search) {
            $qb->andWhere('c.name LIKE :s OR c.email LIKE :s OR c.subject LIKE :s')
                ->setParameter('s', '%'.$search.'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
