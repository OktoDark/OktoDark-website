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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PermissionChecker
{
    public function __construct(
        private Security $security,
        private PermissionRepository $permissionRepository,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function can(string $permissionName): bool
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // Skip permission checks during login or anonymous access
        if (!$user instanceof User) {
            return false;
        }

        $roles = $user->getRoles();

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
