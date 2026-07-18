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

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class PermissionConfigLoader
{
    private string $projectDir;

    public function __construct(ParameterBagInterface $bag)
    {
        $this->projectDir = $bag->get('kernel.project_dir');
    }

    public function loadAll(): array
    {
        $baseDir = $this->projectDir.'/config/permissions';
        $finder = new Finder();
        $finder->files()->in($baseDir)->name('*.yaml');

        $config = [
            'manual_permissions' => [],
            'roles' => [],
        ];

        foreach ($finder as $file) {
            $data = Yaml::parseFile($file->getRealPath()) ?? [];

            if (!empty($data['manual_permissions'])) {
                foreach ($data['manual_permissions'] as $perm) {
                    $config['manual_permissions'][] = $perm;
                }
            }

            if (!empty($data['roles'])) {
                foreach ($data['roles'] as $role => $perms) {
                    if (!isset($config['roles'][$role])) {
                        $config['roles'][$role] = [];
                    }
                    $config['roles'][$role] = array_values(array_unique([
                        ...$config['roles'][$role],
                        ...$perms,
                    ]));
                }
            }
        }

        return $config;
    }
}
