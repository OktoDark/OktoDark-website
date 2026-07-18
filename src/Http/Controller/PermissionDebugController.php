<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */


namespace App\Http\Controller;

use App\Repository\PermissionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PermissionDebugController extends AbstractController
{
    #[Route('/debug/permissions', name: 'debug_permissions')]
    public function index(PermissionRepository $permissionRepository): Response
    {
        $user = $this->getUser();

        $permissions = [];

        if ($user) {
            foreach ($user->getRoleEntities() as $role) {
                foreach ($role->getRolePermissions() as $rp) {
                    if ($rp->isAllowed()) {
                        $permissions[] = $rp->getPermission()->getName();
                    }
                }
            }
        }

        return $this->render('@theme/debug/permissions.html.twig', [
            'permissions' => $permissions,
        ]);
    }
}

