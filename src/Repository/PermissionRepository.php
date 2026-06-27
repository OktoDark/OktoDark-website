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

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, \App\Entity\Permission::class);
    }

    public function rolesHavePermission(array $roleNames, string $permissionName): bool
    {
        if (empty($roleNames)) {
            return false;
        }

        $qb = $this->_em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Permission', 'p')
            ->join('App\Entity\RolePermission', 'rp', 'WITH', 'rp.permission = p.id')
            ->join('App\Entity\Role', 'r', 'WITH', 'rp.role = r.id')
            ->where('p.name = :perm')
            ->andWhere('r.name IN (:roles)')
            ->andWhere('rp.allowed = 1')
            ->setParameter('perm', $permissionName)
            ->setParameter('roles', $roleNames);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
