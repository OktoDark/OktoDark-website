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

use App\Entity\User;
use App\Repository\PermissionRepository;
use Symfony\Bundle\SecurityBundle\Security;

class PermissionChecker
{
    public function __construct(
        private Security $security,
        private PermissionRepository $permissionRepository,
    ) {
    }

    public function can(string $permissionName): bool
    {
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }

        // 1) Get all Symfony roles for this user (from token)
        $roles = $user->getRoles();

        // 2) Ask repository if any of these roles has this permission
        return $this->permissionRepository->rolesHavePermission($roles, $permissionName);
    }

    public function userHasPermission(User $user, string $permissionName): bool
    {
        foreach ($user->getRoleEntities() as $role) {
            foreach ($role->getRolePermissions() as $rp) {
                if ($rp->getPermission()->getName() === $permissionName && $rp->isAllowed()) {
                    return true;
                }
            }
        }

        return false;
    }
}
