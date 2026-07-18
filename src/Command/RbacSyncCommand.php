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
use App\Security\PermissionScanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:rbac:sync',
    description: 'Synchronize RBAC (permissions, groups, mappings) from controllers + YAML into the database'
)]
class RbacSyncCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private PermissionScanner $scanner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔄 RBAC Synchronization');

        $permRepo = $this->em->getRepository(Permission::class);
        $groupRepo = $this->em->getRepository(PermissionGroup::class);
        $roleRepo = $this->em->getRepository(Role::class);
        $rpRepo = $this->em->getRepository(RolePermission::class);

        // 1) Load existing DB state
        /** @var Permission[] $dbPerms */
        $dbPerms = $permRepo->findAll();
        /** @var PermissionGroup[] $dbGroups */
        $dbGroups = $groupRepo->findAll();

        $dbPermMap = [];
        foreach ($dbPerms as $p) {
            $dbPermMap[$p->getName()] = $p;
        }

        $dbGroupMap = [];
        foreach ($dbGroups as $g) {
            $dbGroupMap[$g->getName()] = $g;
        }

        // 2) Load scanner permissions (controllers)
        $io->section('📡 Scanning controllers for permissions');
        $scannerData = $this->scanner->scan(); // assuming it returns an array of permission entries

        $scannerPerms = [];
        foreach ($scannerData as $data) {
            $name = $data['name'] ?? $data['permission'] ?? null;
            if (!$name) {
                continue;
            }
            $label = $data['label'] ?? $name;
            $area = $data['area'] ?? explode('.', $name)[0] ?? 'general';

            // Fallback group only when the attribute supplies no explicit group.
            // The real group/label/area are sourced from the YAML files below,
            // which override the scanner entry when the same permission name exists.
            $prefix = explode('.', $name)[0];
            $groupName = $data['group'] ?? match ($prefix) {
                'forum' => 'Forum',
                'blog' => 'Blog',
                'member' => 'Member',
                'games' => 'Games',
                'admin' => 'Admin',
                'system' => 'System',
                default => 'General',
            };

            $scannerPerms[$name] = [
                'name' => $name,
                'label' => $label,
                'area' => $area,
                'group' => $groupName,
            ];
        }

        // 3) Load YAML manual permissions + role mappings
        //    (all config/permissions/**/*.yaml files)
        $io->section('📁 Loading YAML manual permissions');
        $yamlPerms = [];
        $roleMappings = []; // role name => [permission names]

        $permDir = \dirname(__DIR__, 2).'/config/permissions';
        if (is_dir($permDir)) {
            $finder = (new Finder())->files()->in($permDir)->name('*.yaml');
            foreach ($finder as $file) {
                $yaml = Yaml::parseFile($file->getRealPath()) ?? [];
                if (!empty($yaml['manual_permissions'])) {
                    foreach ($yaml['manual_permissions'] as $data) {
                        $name = $data['name'] ?? null;
                        if (!$name) {
                            continue;
                        }
                        $label = $data['label'] ?? $name;
                        $area = $data['area'] ?? explode('.', $name)[0] ?? 'general';
                        $groupName = $data['group'] ?? 'General';

                        $yamlPerms[$name] = [
                            'name' => $name,
                            'label' => $label,
                            'area' => $area,
                            'group' => $groupName,
                        ];
                    }
                }
                if (!empty($yaml['roles'])) {
                    foreach ($yaml['roles'] as $role => $perms) {
                        foreach ($perms as $permName) {
                            $roleMappings[$role][] = $permName;
                        }
                    }
                }
            }
        }

        // 4) Build desired permission set (scanner + YAML)
        $desiredPerms = $scannerPerms + $yamlPerms; // YAML overrides scanner if same name

        // 5) Ensure groups exist
        $io->section('🏷 Ensuring groups exist');
        foreach ($desiredPerms as $permData) {
            $groupName = $permData['group'];
            if (!isset($dbGroupMap[$groupName])) {
                $group = new PermissionGroup();
                $group->setName($groupName);
                $group->setLabel($groupName);
                $this->em->persist($group);
                $dbGroupMap[$groupName] = $group;
                $io->text("Created group '$groupName'");
            }
        }

        // 6) Create/update permissions to match desired set
        $io->section('🔐 Synchronizing permissions');
        $created = [];
        $updated = [];

        foreach ($desiredPerms as $name => $data) {
            $label = $data['label'];
            $area = $data['area'];
            $groupName = $data['group'];
            $group = $dbGroupMap[$groupName] ?? null;

            if (!isset($dbPermMap[$name])) {
                $perm = new Permission();
                $perm->setName($name);
                $perm->setLabel($label);
                $perm->setArea($area);
                $perm->setGroup($group);

                $this->em->persist($perm);
                $dbPermMap[$name] = $perm;
                $created[] = $name;
            } else {
                $perm = $dbPermMap[$name];
                $changed = false;

                if ($perm->getLabel() !== $label) {
                    $perm->setLabel($label);
                    $changed = true;
                }
                if ($perm->getArea() !== $area) {
                    $perm->setArea($area);
                    $changed = true;
                }
                if ($perm->getGroup() !== $group) {
                    $perm->setGroup($group);
                    $changed = true;
                }

                if ($changed) {
                    $updated[] = $name;
                }
            }
        }

        // 7) Remove deprecated permissions (in DB but not in desired set)
        $io->section('🗑 Removing deprecated permissions');
        $removed = [];

        foreach ($dbPermMap as $name => $perm) {
            if (!isset($desiredPerms[$name])) {
                // remove role-permission mappings first
                $rps = $rpRepo->findBy(['permission' => $perm]);
                foreach ($rps as $rp) {
                    $this->em->remove($rp);
                }

                $this->em->remove($perm);
                unset($dbPermMap[$name]);
                $removed[] = $name;
            }
        }

        // 8) Apply role → permission mappings from YAML 'roles:' blocks.
        //    Idempotent: only creates missing RolePermission rows, never removes
        //    (so manual/admin assignments are preserved). Permissions must exist
        //    first, which is guaranteed by step 6.
        $io->section('🔗 Applying role → permission mappings');
        $roleMap = [];
        foreach ($roleRepo->findAll() as $r) {
            $roleMap[$r->getName()] = $r;
        }

        $assigned = [];
        foreach ($roleMappings as $roleName => $permNames) {
            if (!isset($roleMap[$roleName])) {
                $io->text("Skipped mapping for unknown role '$roleName'");
                continue;
            }
            $role = $roleMap[$roleName];

            foreach (array_unique($permNames) as $permName) {
                if (!isset($dbPermMap[$permName])) {
                    // Permission not present in the desired set; ignore.
                    continue;
                }
                $perm = $dbPermMap[$permName];
                $existing = $rpRepo->findOneBy(['role' => $role, 'permission' => $perm]);
                if ($existing) {
                    if (!$existing->isAllowed()) {
                        $existing->setAllowed(true);
                        $assigned[] = "$roleName → $permName (re-enabled)";
                    }
                    continue;
                }
                $rp = new RolePermission();
                $rp->setRole($role);
                $rp->setPermission($perm);
                $rp->setAllowed(true);
                $this->em->persist($rp);
                $assigned[] = "$roleName → $permName";
            }
        }

        // 9) Flush changes
        $this->em->flush();

        // 10) Report
        if (empty($created) && empty($updated) && empty($removed)) {
            $io->success('RBAC is already in perfect sync.');

            return Command::SUCCESS;
        }

        if (!empty($created)) {
            $io->section('🆕 Created Permissions');
            foreach ($created as $name) {
                $io->writeln("  • <info>$name</info>");
            }
        }

        if (!empty($updated)) {
            $io->section('♻️ Updated Permissions');
            foreach ($updated as $name) {
                $io->writeln("  • <comment>$name</comment>");
            }
        }

        if (!empty($removed)) {
            $io->section('🗑 Removed Deprecated Permissions');
            foreach ($removed as $name) {
                $io->writeln("  • <error>$name</error>");
            }
        }

        if (!empty($assigned)) {
            $io->section('🔗 Assigned Role Mappings');
            foreach ($assigned as $mapping) {
                $io->writeln("  • <info>$mapping</info>");
            }
        }

        $io->success('RBAC synchronization complete.');

        return Command::SUCCESS;
    }
}
