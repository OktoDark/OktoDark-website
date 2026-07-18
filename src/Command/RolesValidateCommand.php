<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Command;

use App\Entity\Permission;
use App\Entity\PermissionGroup;
use App\Entity\Role;
use App\Entity\RolePermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:roles:validate',
    description: 'Validate roles, permissions, groups, and mappings'
)]
class RolesValidateCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔍 RBAC Validation Report');

        $roleRepo = $this->em->getRepository(Role::class);
        $permRepo = $this->em->getRepository(Permission::class);
        $groupRepo = $this->em->getRepository(PermissionGroup::class);
        $rpRepo = $this->em->getRepository(RolePermission::class);

        $roles = $roleRepo->findAll();
        $perms = $permRepo->findAll();
        $groups = $groupRepo->findAll();
        $rps = $rpRepo->findAll();

        $errors = [];

        // 1. Roles with no permissions
        foreach ($roles as $role) {
            if (0 === $role->getRolePermissions()->count()) {
                $errors[] = "Role '{$role->getName()}' has NO permissions.";
            }
        }

        // 2. Permissions with no group
        foreach ($perms as $perm) {
            if (!$perm->getGroup()) {
                $errors[] = "Permission '{$perm->getName()}' has NO group.";
            }
        }

        // 3. Permissions with no roles
        foreach ($perms as $perm) {
            if (0 === $perm->getRolePermissions()->count()) {
                $errors[] = "Permission '{$perm->getName()}' is not assigned to ANY role.";
            }
        }

        // 4. Orphaned RolePermission entries
        foreach ($rps as $rp) {
            if (!$rp->getRole() || !$rp->getPermission()) {
                $errors[] = 'Orphaned RolePermission entry detected.';
            }
        }

        // 5. Missing groups
        if (empty($groups)) {
            $errors[] = 'No PermissionGroups exist.';
        }

        if (empty($errors)) {
            $io->success('RBAC system is valid. No issues found.');

            return Command::SUCCESS;
        }

        $io->section('❌ Issues Found');
        foreach ($errors as $err) {
            $io->writeln(" • <error>$err</error>");
        }

        $io->warning('Validation completed with errors.');

        return Command::FAILURE;
    }
}
