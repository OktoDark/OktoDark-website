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
use Doctrine\ORM\EntityManagerInterface;

class RoleValidator
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function validate(Role $role): array
    {
        $errors = [];

        // Check if role has permissions
        if (0 === $role->getRolePermissions()->count()) {
            $errors[] = "Role '{$role->getName()}' has NO permissions assigned.";
        }

        // Check if role has a label
        if (!$role->getLabel()) {
            $errors[] = "Role '{$role->getName()}' has no label.";
        }

        return $errors;
    }
}
