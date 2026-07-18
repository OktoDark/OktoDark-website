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
    name: 'app:roles:repair',
    description: 'Automatically repair roles, permissions, groups, and mappings'
)]
class RolesRepairCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🛠 RBAC Auto-Repair Tool');

        $roleRepo = $this->em->getRepository(Role::class);
        $permRepo = $this->em->getRepository(Permission::class);
        $groupRepo = $this->em->getRepository(PermissionGroup::class);
        $rpRepo = $this->em->getRepository(RolePermission::class);

        $roles = $roleRepo->findAll();
        $perms = $permRepo->findAll();
        $groups = $groupRepo->findAll();
        $rps = $rpRepo->findAll();

        $changes = [];

        // 1. Create default group if missing
        if (!$groupRepo->findOneBy(['name' => 'General'])) {
            $g = new PermissionGroup();
            $g->setName('General');
            $g->setLabel('General');
            $this->em->persist($g);
            $changes[] = "Created missing PermissionGroup 'General'.";
        }

        // 2. Assign missing permissions to General group
        foreach ($perms as $perm) {
            if (!$perm->getGroup()) {
                $group = $groupRepo->findOneBy(['name' => 'General']);
                $perm->setGroup($group);
                $changes[] = "Assigned permission '{$perm->getName()}' to group 'General'.";
            }
        }

        // 3. Remove orphaned RolePermission entries
        foreach ($rps as $rp) {
            if (!$rp->getRole() || !$rp->getPermission()) {
                $this->em->remove($rp);
                $changes[] = 'Removed orphaned RolePermission entry.';
            }
        }

        // 4. Ensure every role has at least 1 permission
        foreach ($roles as $role) {
            if (0 === $role->getRolePermissions()->count()) {
                $perm = $permRepo->findOneBy([]); // first permission
                if ($perm) {
                    $rp = new RolePermission();
                    $rp->setRole($role);
                    $rp->setPermission($perm);
                    $rp->setAllowed(true);
                    $this->em->persist($rp);
                    $changes[] = "Assigned default permission '{$perm->getName()}' to role '{$role->getName()}'.";
                }
            }
        }

        // 5. Ensure every permission is assigned to at least 1 role
        foreach ($perms as $perm) {
            if (0 === $perm->getRolePermissions()->count()) {
                $role = $roleRepo->findOneBy(['name' => 'ROLE_ADMIN']);
                if ($role) {
                    $rp = new RolePermission();
                    $rp->setRole($role);
                    $rp->setPermission($perm);
                    $rp->setAllowed(true);
                    $this->em->persist($rp);
                    $changes[] = "Assigned permission '{$perm->getName()}' to ROLE_ADMIN.";
                }
            }
        }

        $this->em->flush();

        if (empty($changes)) {
            $io->success('RBAC system is already healthy. No repairs needed.');

            return Command::SUCCESS;
        }

        $io->section('🔧 Repairs Applied');
        foreach ($changes as $c) {
            $io->writeln(" • <info>$c</info>");
        }

        $io->success('RBAC repair completed successfully.');

        return Command::SUCCESS;
    }
}
