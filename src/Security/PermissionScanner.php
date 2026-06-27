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

use App\Entity\Permission;
use App\Entity\PermissionGroup;
use App\Security\Attribute\Permission as PermissionAttribute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Finder\Finder;

class PermissionScanner
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $controllerPath = __DIR__.'/../Controller',
    ) {
    }

    public function scan(): array
    {
        $foundPermissions = [];

        $finder = new Finder();
        $finder->files()->in($this->controllerPath)->name('*.php');

        foreach ($finder as $file) {
            $className = $this->extractClassName($file->getRealPath());
            if (!$className || !class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            // Scan class-level attributes
            $this->scanAttributes($reflection, $foundPermissions);

            // Scan method-level attributes
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $this->scanAttributes($method, $foundPermissions);
            }
        }

        $this->syncPermissions($foundPermissions);
        return $foundPermissions;
    }

    private function scanAttributes(\ReflectionClass|\ReflectionMethod $ref, array &$foundPermissions): void
    {
        foreach ($ref->getAttributes(PermissionAttribute::class) as $attr) {
            /** @var PermissionAttribute $instance */
            $instance = $attr->newInstance();

            $foundPermissions[] = [
                'name' => $instance->name,
                'group' => $instance->group ?? 'General',
                'label' => $instance->label ?? $instance->name,
            ];
        }
    }

    private function syncPermissions(array $found): array
    {
        $repoPerm = $this->em->getRepository(Permission::class);
        $repoGroup = $this->em->getRepository(PermissionGroup::class);

        $added = [];
        $updated = [];
        $groupsAdded = [];
        $createdGroups = [];
        $createdPermissions = [];

        foreach ($found as $permData) {
            $group = $repoGroup->findOneBy(['name' => $permData['group']]);

            if (!$group) {
                // Check if we already created this group in this batch
                if (isset($createdGroups[$permData['group']])) {
                    $group = $createdGroups[$permData['group']];
                } else {
                    $group = new PermissionGroup();
                    $group->setName($permData['group']);
                    $group->setLabel($permData['group']);

                    $this->em->persist($group);
                    $createdGroups[$permData['group']] = $group;
                    $groupsAdded[] = $permData['group'];
                }
            }

            $perm = $repoPerm->findOneBy(['name' => $permData['name']]);

            if (!$perm) {
                // Check if we already created this permission in this batch
                if (isset($createdPermissions[$permData['name']])) {
                    $perm = $createdPermissions[$permData['name']];
                } else {
                    $perm = new Permission();
                    $perm->setName($permData['name']);
                    $perm->setLabel($permData['label']);
                    $perm->setGroup($group);
                    $perm->setArea($this->extractArea($permData['name']));

                    $this->em->persist($perm);
                    $createdPermissions[$permData['name']] = $perm;
                    $added[] = $permData['name'];
                }
            } else {
                $changed = false;

                if ($perm->getLabel() !== $permData['label']) {
                    $perm->setLabel($permData['label']);
                    $changed = true;
                }

                if ($perm->getGroup()?->getName() !== $permData['group']) {
                    $perm->setGroup($group);
                    $changed = true;
                }

                if ($perm->getArea() !== $this->extractArea($permData['name'])) {
                    $perm->setArea($this->extractArea($permData['name']));
                    $changed = true;
                }

                if ($changed) {
                    $updated[] = $permData['name'];
                }
            }
        }

        $this->em->flush();

        return [
            'added_permissions' => $added,
            'updated_permissions' => $updated,
            'added_groups' => $groupsAdded,
        ];
    }

    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if (!preg_match('/namespace\s+(.+?);/', $content, $nsMatch)) {
            return null;
        }

        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $nsMatch[1].'\\'.$classMatch[1];
    }

    private function extractArea(string $permissionName): string
    {
        // Extract area from permission name like "blog.view" -> "blog"
        $parts = explode('.', $permissionName);
        return $parts[0] ?? 'general';
    }
}
