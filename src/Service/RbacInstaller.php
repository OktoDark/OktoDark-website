<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Entity\Permission;
use App\Entity\PermissionGroup;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Security\PermissionScanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class RbacInstaller
{
    public function __construct(
        private EntityManagerInterface $em,
        private PermissionScanner $scanner,
    ) {
    }

    public function install(SymfonyStyle $io): void
    {
        $io->section('RBAC install: roles, groups, permissions, mappings');

        $this->createDefaultGroups($io);
        $this->createDefaultRoles($io);
        $this->scanAndCreatePermissions($io);
        $this->applyDefaultMappings($io);

        $this->em->flush();

        $io->success('RBAC install completed.');
    }

    private function createDefaultGroups(SymfonyStyle $io): void
    {
        $groups = [
            ['name' => 'Admin',   'label' => 'Admin'],
            ['name' => 'Forum',   'label' => 'Forum'],
            ['name' => 'Blog',    'label' => 'Blog'],
            ['name' => 'Member',  'label' => 'Member'],
            ['name' => 'Games',   'label' => 'Games'],
            ['name' => 'System',  'label' => 'System'],
            ['name' => 'General', 'label' => 'General'],
        ];

        $repo = $this->em->getRepository(PermissionGroup::class);

        foreach ($groups as $g) {
            if ($repo->findOneBy(['name' => $g['name']])) {
                continue;
            }

            $group = new PermissionGroup();
            $group->setName($g['name']);
            $group->setLabel($g['label']);

            $this->em->persist($group);
            $io->text("Created group '{$g['name']}'");
        }
    }

    private function createDefaultRoles(SymfonyStyle $io): void
    {
        $roles = [
            'ROLE_USER',
            'ROLE_ADMIN',
            'ROLE_SUPER_ADMIN',
            'ROLE_MOD_CREATOR',
            'ROLE_DONOR',
            'ROLE_VIP',
            'ROLE_TIER_I',
            'ROLE_TIER_II',
            'ROLE_TIER_III',
            'ROLE_TIER_IV',
            'ROLE_TIER_V',
        ];

        $repo = $this->em->getRepository(Role::class);

        foreach ($roles as $name) {
            if ($repo->findOneBy(['name' => $name])) {
                continue;
            }

            $role = new Role();
            $role->setName($name);
            $role->setLabel($name);

            $this->em->persist($role);
            $io->text("Created role '$name'");
        }
    }

    private function scanAndCreatePermissions(SymfonyStyle $io): void
    {
        $io->text('Scanning controllers for permissions...');

        $permRepo = $this->em->getRepository(Permission::class);
        $groupRepo = $this->em->getRepository(PermissionGroup::class);

        // 1) Load manual permissions from all YAML files
        $manual = $this->loadPermissionsYaml();

        if (!empty($manual['manual_permissions'])) {
            foreach ($manual['manual_permissions'] as $data) {
                $name = $data['name'];
                $label = $data['label'] ?? $name;
                $area = $data['area'] ?? explode('.', $name)[0] ?? 'general';
                $groupName = $data['group'] ?? 'General';

                $group = $groupRepo->findOneBy(['name' => $groupName]);
                if (!$group) {
                    // $group = new PermissionGroup();
                    // $group->setName($groupName);
                    // $group->setLabel($groupName);
                    // $this->em->persist($group);
                    $io->text("Created group '$groupName' (manual)");
                }

                $perm = $permRepo->findOneBy(['name' => $name]);
                if (!$perm) {
                    $perm = new Permission();
                    $perm->setName($name);
                    $io->text("Created manual permission '$name'");
                }

                $perm->setLabel($label);
                $perm->setArea($area);
                $perm->setGroup($group);

                $this->em->persist($perm);
            }
        }

        // 2) Scanner-based permissions
        $permissionsData = $this->scanner->scan();

        // Prefer YAML-defined group/label/area for scanned permissions.
        $yamlByName = [];
        foreach ($manual['manual_permissions'] ?? [] as $entry) {
            if (!empty($entry['name'])) {
                $yamlByName[$entry['name']] = $entry;
            }
        }

        foreach ($permissionsData as $data) {
            $name = $data['name'] ?? $data['permission'] ?? null;

            if (!$name) {
                $io->warning('PermissionScanner returned an entry without a name. Skipping.');
                continue;
            }

            $yamlEntry = $yamlByName[$name] ?? null;
            $label = $data['label'] ?? $yamlEntry['label'] ?? $name;
            $area = $data['area'] ?? $yamlEntry['area'] ?? explode('.', $name)[0] ?? 'general';

            $prefix = explode('.', $name)[0];

            $groupName = $data['group']
                ?? $yamlEntry['group']
                ?? match ($prefix) {
                    'forum' => 'Forum',
                    'blog' => 'Blog',
                    'member' => 'Member',
                    'games' => 'Games',
                    'admin' => 'Admin',
                    'system' => 'System',
                    default => 'General',
                };

            $group = $groupRepo->findOneBy(['name' => $groupName]);
            if (!$group) {
                $group = new PermissionGroup();
                $group->setName($groupName);
                $group->setLabel($groupName);
                $this->em->persist($group);
                $io->text("Created group '$groupName' (auto)");
            }

            $perm = $permRepo->findOneBy(['name' => $name]);
            if (!$perm) {
                $perm = new Permission();
                $perm->setName($name);
                $io->text("Created permission '$name'");
            }

            $perm->setLabel($label);
            $perm->setArea($area);
            $perm->setGroup($group);

            $this->em->persist($perm);
        }
    }

    private function loadYaml(string $filename): array
    {
        $path = \dirname(__DIR__, 2).'/config/'.$filename;

        if (!file_exists($path)) {
            return [];
        }

        return \Symfony\Component\Yaml\Yaml::parseFile($path) ?? [];
    }

    /**
     * Loads manual_permissions from every config/permissions YAML file (top
     * level and sub-directories), matching the behaviour of the
     * PermissionConfigLoader used by the UI. Scanned controller permissions
     * fall back to this data for their group, label and area so dedicated
     * groups (Tracker, Kanban, Mods, ...) are preserved instead of being
     * forced into the 'General' group.
     */
    private function loadPermissionsYaml(): array
    {
        $dir = \dirname(__DIR__, 2).'/config/permissions';
        $config = ['manual_permissions' => [], 'roles' => []];

        if (!is_dir($dir)) {
            return $config;
        }

        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in($dir)->name('*.yaml');

        foreach ($finder as $file) {
            $data = \Symfony\Component\Yaml\Yaml::parseFile($file->getRealPath()) ?? [];
            foreach ($data['manual_permissions'] ?? [] as $perm) {
                if (!empty($perm['name'])) {
                    $config['manual_permissions'][] = $perm;
                }
            }
            foreach ($data['roles'] ?? [] as $role => $perms) {
                foreach ($perms as $permName) {
                    $config['roles'][$role][] = $permName;
                }
            }
        }

        return $config;
    }

    /**
     * Applies the 'roles:' mappings declared in the permission YAML files as
     * RolePermission rows. Idempotent: only adds missing assignments, never
     * removes existing ones, so manual admin/super-admin grants are preserved.
     */
    private function applyYamlRoleMappings(SymfonyStyle $io, array $manual): void
    {
        $roleRepo = $this->em->getRepository(Role::class);
        $permRepo = $this->em->getRepository(Permission::class);
        $rpRepo = $this->em->getRepository(RolePermission::class);

        $roleMap = [];
        foreach ($roleRepo->findAll() as $r) {
            $roleMap[$r->getName()] = $r;
        }

        foreach ($manual['roles'] ?? [] as $roleName => $permNames) {
            if (!isset($roleMap[$roleName])) {
                $io->text("Skipped mapping for unknown role '$roleName'");
                continue;
            }
            $role = $roleMap[$roleName];

            foreach (array_unique($permNames) as $permName) {
                $perm = $permRepo->findOneBy(['name' => $permName]);
                if (!$perm instanceof Permission) {
                    continue;
                }
                if ($rpRepo->findOneBy(['role' => $role, 'permission' => $perm])) {
                    continue;
                }
                $rp = new RolePermission();
                $rp->setRole($role);
                $rp->setPermission($perm);
                $rp->setAllowed(true);
                $this->em->persist($rp);
                $io->text("Mapped '$permName' → $roleName");
            }
        }
    }

    private function applyDefaultMappings(SymfonyStyle $io): void
    {
        $roleRepo = $this->em->getRepository(Role::class);
        $permRepo = $this->em->getRepository(Permission::class);
        $rpRepo = $this->em->getRepository(RolePermission::class);
        $groupRepo = $this->em->getRepository(PermissionGroup::class);

        // RBAC CLEANUP: remove deprecated or duplicate permissions
        $deprecated = [
            'system.maintenance.bypass',
        ];

        foreach ($deprecated as $permName) {
            $perm = $permRepo->findOneBy(['name' => $permName]);
            if ($perm instanceof Permission) {
                $this->em->remove($perm);
                $io->text("Removed deprecated permission '$permName'");
            }
        }

        $admin = $roleRepo->findOneBy(['name' => 'ROLE_ADMIN']);
        $super = $roleRepo->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);

        if (!$admin || !$super) {
            $io->warning('ROLE_ADMIN or ROLE_SUPER_ADMIN missing, skipping mappings.');

            return;
        }

        // ADMIN gets admin.*, forum.*, blog.*, member.*, games.*
        $adminPerms = $permRepo->createQueryBuilder('p')
            ->where('p.name LIKE :admin OR p.name LIKE :forum OR p.name LIKE :blog OR p.name LIKE :member OR p.name LIKE :games')
            ->setParameter('admin', 'admin.%')
            ->setParameter('forum', 'forum.%')
            ->setParameter('blog', 'blog.%')
            ->setParameter('member', 'member.%')
            ->setParameter('games', 'games.%')
            ->getQuery()
            ->getResult();

        foreach ($adminPerms as $perm) {
            if (!$rpRepo->findOneBy(['role' => $admin, 'permission' => $perm])) {
                $rp = new RolePermission();
                $rp->setRole($admin);
                $rp->setPermission($perm);
                $rp->setAllowed(true);
                $this->em->persist($rp);
            }
        }

        // SUPER ADMIN gets everything
        foreach ($permRepo->findAll() as $perm) {
            if (!$rpRepo->findOneBy(['role' => $super, 'permission' => $perm])) {
                $rp = new RolePermission();
                $rp->setRole($super);
                $rp->setPermission($perm);
                $rp->setAllowed(true);
                $this->em->persist($rp);
            }
        }

        // Assign maintenance.bypass to ADMIN + SUPER_ADMIN
        $maintPerm = $permRepo->findOneBy(['name' => 'maintenance.bypass']);

        if ($maintPerm instanceof Permission) {
            foreach ([$admin, $super] as $role) {
                if (!$rpRepo->findOneBy(['role' => $role, 'permission' => $maintPerm])) {
                    $rp = new RolePermission();
                    $rp->setRole($role);
                    $rp->setPermission($maintPerm);
                    $rp->setAllowed(true);
                    $this->em->persist($rp);
                    $io->text("Assigned 'maintenance.bypass' to {$role->getName()}");
                }
            }
        }

        $io->text('Default role → permission mappings applied (ADMIN + SUPER_ADMIN).');

        // Apply explicit role → permission mappings from the permission YAML
        // files (e.g. tracker.yaml → ROLE_USER). Runs last so all permissions
        // created above are available.
        $this->applyYamlRoleMappings($io, $this->loadPermissionsYaml());
    }

    public function installSilently(): void
    {
        $this->createDefaultGroups(new SymfonyStyle(
            new ArrayInput([]),
            new NullOutput()
        ));

        $this->createDefaultRoles(new SymfonyStyle(
            new ArrayInput([]),
            new NullOutput()
        ));

        $this->scanAndCreatePermissions(new SymfonyStyle(
            new ArrayInput([]),
            new NullOutput()
        ));

        $this->applyDefaultMappings(new SymfonyStyle(
            new ArrayInput([]),
            new NullOutput()
        ));

        $this->em->flush();
    }
}
