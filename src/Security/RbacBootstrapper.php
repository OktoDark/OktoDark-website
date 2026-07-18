<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Security;

use App\Entity\Role;
use App\Entity\User;
use App\Service\RbacInstaller;
use Doctrine\ORM\EntityManagerInterface;

class RbacBootstrapper
{
    public function __construct(
        private EntityManagerInterface $em,
        private RbacInstaller $installer,
    ) {
    }

    public function bootstrapIfNeeded(User $user): void
    {
        $roleRepo = $this->em->getRepository(Role::class);
        $userRepo = $this->em->getRepository(User::class);

        // 1) Check if SUPER ADMIN role exists
        $superRole = $roleRepo->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);

        if (!$superRole) {
            // RBAC not installed → install silently
            $this->installer->installSilently();
            $superRole = $roleRepo->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        }

        // 2) Check if any SUPER ADMIN user exists
        $existingSuperAdmins = $userRepo->createQueryBuilder('u')
            ->join('u.roleEntities', 'r')
            ->andWhere('r.name = :role')
            ->setParameter('role', 'ROLE_SUPER_ADMIN')
            ->getQuery()
            ->getResult();

        if (empty($existingSuperAdmins)) {
            // First user ever → make SUPER ADMIN
            $user->addRoleEntity($superRole);
        } else {
            // Normal user → assign ROLE_USER
            $userRole = $roleRepo->findOneBy(['name' => 'ROLE_USER']);
            if ($userRole) {
                $user->addRoleEntity($userRole);
            }
        }
    }
}
