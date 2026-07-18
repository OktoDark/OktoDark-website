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
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/rbac/api')]
class RBACApiController extends AbstractController
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
     * Return roles, manual permissions and scanned controller attributes as JSON.
     */
    #[Route('/data', name: 'admin_rbac_api_data')]
    public function data()
    {
        return $this->json([
            'roles' => $this->loader->loadAll()['roles'],
            'permissions' => $this->loader->loadAll()['manual_permissions'],
            'attributes' => $this->scanner->scanControllers(),
        ]);
    }
}
