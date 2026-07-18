<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\RBAC;

final class PermissionInheritanceResolver
{
    private array $inheritance = [
        'ROLE_CONTENT_CREATOR' => ['ROLE_USER'],
        'ROLE_MOD_CREATOR' => ['ROLE_USER'],
        'ROLE_MODERATOR' => ['ROLE_USER'],
        'ROLE_ADMIN' => ['ROLE_MODERATOR', 'ROLE_CONTENT_CREATOR'],
        'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
    ];

    public function resolve(array $roles): array
    {
        $resolved = [];

        foreach ($roles as $role => $perms) {
            $resolved[$role] = $perms;

            if (!empty($this->inheritance[$role])) {
                foreach ($this->inheritance[$role] as $parent) {
                    if (isset($roles[$parent])) {
                        $resolved[$role] = array_unique([
                            ...$resolved[$role],
                            ...$roles[$parent],
                        ]);
                    }
                }
            }
        }

        return $resolved;
    }
}

