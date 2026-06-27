<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\DataFixtures;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\RolePermission;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RolePermissionSeeder extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // ---------------------------------------------------------
        // 1. DEFAULT ROLES
        // ---------------------------------------------------------
        $roles = [
            'ROLE_USER' => 'Standard User',
            'ROLE_ADMIN' => 'Administrator',
            'ROLE_SUPER_ADMIN' => 'Super Administrator',
            'ROLE_MODERATOR' => 'Forum Moderator',
            'ROLE_SUPPORT' => 'Support Staff',
            'ROLE_CREATOR' => 'Content Creator',
            'ROLE_MOD_CREATOR' => 'Mod Creator',
            'ROLE_DONOR' => 'Donor',
            'ROLE_VIP' => 'VIP',
            'ROLE_TIER_I' => 'Tier I',
            'ROLE_TIER_II' => 'Tier II',
            'ROLE_TIER_III' => 'Tier III',
            'ROLE_TIER_IV' => 'Tier IV',
            'ROLE_TIER_V' => 'Tier V',
        ];

        $roleEntities = [];

        foreach ($roles as $name => $label) {
            $role = new Role();
            $role->setName($name);
            $role->setLabel($label);

            $manager->persist($role);
            $roleEntities[$name] = $role;
        }

        // ---------------------------------------------------------
        // 2. DEFAULT PERMISSIONS
        // ---------------------------------------------------------
        $permissions = [
            // Forum
            'forum.thread.create' => ['Forum', 'Create threads'],
            'forum.thread.delete' => ['Forum', 'Delete threads'],
            'forum.post.create' => ['Forum', 'Create posts'],
            'forum.post.delete' => ['Forum', 'Delete posts'],
            'forum.post.edit' => ['Forum', 'Edit posts'],

            // Moderation
            'mod.ban.user' => ['Moderation', 'Ban users'],
            'mod.unban.user' => ['Moderation', 'Unban users'],
            'mod.view.reports' => ['Moderation', 'View reports'],
            'mod.resolve.reports' => ['Moderation', 'Resolve reports'],

            // Admin
            'admin.access' => ['Admin', 'Access admin panel'],
            'admin.roles.manage' => ['Admin', 'Manage roles'],
            'admin.permissions' => ['Admin', 'Manage permissions'],
            'admin.users.manage' => ['Admin', 'Manage users'],

            // Uploads
            'upload.files' => ['Uploads', 'Upload files'],
            'upload.images' => ['Uploads', 'Upload images'],
        ];

        $permissionEntities = [];

        foreach ($permissions as $name => [$area, $label]) {
            $perm = new Permission();
            $perm->setName($name);
            $perm->setArea($area);
            $perm->setLabel($label);

            $manager->persist($perm);
            $permissionEntities[$name] = $perm;
        }

        // ---------------------------------------------------------
        // 3. ROLE → PERMISSION ASSIGNMENTS
        // ---------------------------------------------------------

        $assign = function (string $roleName, array $permissionNames) use ($manager, $roleEntities, $permissionEntities) {
            $role = $roleEntities[$roleName];

            foreach ($permissionNames as $permName) {
                $rp = new RolePermission();
                $rp->setRole($role);
                $rp->setPermission($permissionEntities[$permName]);
                $rp->setAllowed(true);

                $manager->persist($rp);
            }
        };

        // ROLE_USER
        $assign('ROLE_USER', [
            'forum.thread.create',
            'forum.post.create',
            'upload.images',
        ]);

        // ROLE_CREATOR
        $assign('ROLE_CREATOR', [
            'upload.files',
            'upload.images',
            'forum.post.edit',
        ]);

        // ROLE_MODERATOR
        $assign('ROLE_MODERATOR', [
            'forum.thread.delete',
            'forum.post.delete',
            'mod.view.reports',
            'mod.resolve.reports',
        ]);

        // ROLE_SUPPORT
        $assign('ROLE_SUPPORT', [
            'mod.view.reports',
            'mod.resolve.reports',
        ]);

        // ROLE_ADMIN
        $assign('ROLE_ADMIN', array_keys($permissions)); // ALL permissions

        // ---------------------------------------------------------
        // SAVE EVERYTHING
        // ---------------------------------------------------------
        $manager->flush();
    }
}
