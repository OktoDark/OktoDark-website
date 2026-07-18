<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin\RBAC;

use App\RBAC\PermissionAttributeScanner;
use App\RBAC\PermissionConfigLoader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/rbac')]
final class RBACDebugController extends AbstractController
{
    /**
     * Initialize controller dependencies for loading and scanning RBAC configuration.
     */
    public function __construct(
        private PermissionConfigLoader $loader,
        private PermissionAttributeScanner $scanner,
    ) {
    }

    /**
     * Render the RBAC debug view with loaded config, scanned attributes and health checks.
     *
     * Builds a permission-to-roles map and computes health checks for orphan
     * permissions, permissions undefined in config, and roles without permissions.
     */
    #[Route('/debug', name: 'admin_rbac_debug')]
    public function debug(): Response
    {
        $config = $this->loader->loadAll();
        $attributes = $this->scanner->scanControllers();

        $manualPermissions = $config['manual_permissions'];
        $roleMap = $config['roles'];

        // Build permission → roles map
        $permissionRoles = [];
        foreach ($roleMap as $role => $perms) {
            foreach ($perms as $perm) {
                $permissionRoles[$perm][] = $role;
            }
        }

        // Health checks
        $health = [
            'orphan_permissions' => array_filter($manualPermissions, static fn ($p) => empty($permissionRoles[$p['name']] ?? [])
            ),
            'undefined_permissions' => array_filter($attributes, static fn ($attr) => !\in_array($attr['name'], array_column($manualPermissions, 'name'), true)
            ),
            'roles_without_permissions' => array_filter(array_keys($roleMap), static fn ($role) => empty($roleMap[$role])
            ),
        ];

        return $this->render('@theme/admin/rbac/debug.html.twig', [
            'manualPermissions' => $manualPermissions,
            'roleMap' => $roleMap,
            'permissionRoles' => $permissionRoles,
            'attributes' => $attributes,
            'health' => $health,
        ]);
    }
}
