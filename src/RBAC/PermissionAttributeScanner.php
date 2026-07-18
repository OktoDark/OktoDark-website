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

final class PermissionAttributeScanner
{
    private string $projectDir;

    public function __construct(ParameterBagInterface $bag)
    {
        $this->projectDir = $bag->get('kernel.project_dir');
    }


    public function scanControllers(): array
    {
        $finder = new Finder();
        $finder->files()->in($this->projectDir.'/src/Controller')->name('*.php');

        $results = [];

        foreach ($finder as $file) {
            $className = $this->getClassFromFile($file->getRealPath());
            if (!$className || !class_exists($className)) {
                continue;
            }

            $ref = new \ReflectionClass($className);

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes() as $attr) {
                    if ('App\Security\Attribute\Permission' === $attr->getName()) {
                        $args = $attr->getArguments();
                        $results[] = [
                            'controller' => $className,
                            'method' => $method->getName(),
                            'name' => $args['value'] ?? $args[0] ?? null,
                            'group' => $args['group'] ?? null,
                            'label' => $args['label'] ?? null,
                        ];
                    }
                }
            }
        }

        return $results;
    }

    private function getClassFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);
        if (!preg_match('/namespace\s+(.+?);/s', $contents, $ns)) {
            return null;
        }
        if (!preg_match('/class\s+(\w+)/s', $contents, $class)) {
            return null;
        }

        return $ns[1].'\\'.$class[1];
    }
}
