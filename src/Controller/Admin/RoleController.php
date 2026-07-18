<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\RolePermission;
use App\Form\Admin\RoleType;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/admin/roles')]
class RoleController extends AbstractController
{
    /**
     * List all roles.
     */
    #[Route('/', name: 'admin_roles')]
    #[Permission('admin.roles.index')]
    public function index(RoleRepository $repo): Response
    {
        return $this->render('admin/roles/index.html.twig', [
            'roles' => $repo->findAll(),
        ]);
    }

    /**
     * Create a new role via the dedicated form type.
     */
    #[Route('/create', name: 'admin_roles_create')]
    #[Permission('admin.roles.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $role = new Role();
        $form = $this->createForm(RoleType::class, $role);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($role);
            $em->flush();

            return $this->redirectToRoute('admin_roles');
        }

        return $this->render('admin/roles/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Create Role',
        ]);
    }

    /**
     * Edit an existing role via the dedicated form type.
     */
    #[Route('/{id}/edit', name: 'admin_roles_edit')]
    #[Permission('admin.roles.edit')]
    public function edit(Role $role, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RoleType::class, $role);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('admin_roles');
        }

        return $this->render('admin/roles/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit Role',
        ]);
    }

    /**
     * Manage a role's permission assignments via toggle checkboxes.
     *
     * On POST, it creates or updates a RolePermission row (allowed flag) for each
     * permission and persists the changes, then redirects back to this page.
     */
    #[Route('/{id}/permissions', name: 'admin_roles_permissions')]
    #[Permission('admin.roles.permissions')]
    public function permissions(
        Role $role,
        PermissionRepository $permRepo,
        EntityManagerInterface $em,
        Request $request,
    ): Response {
        $permissions = $permRepo->findAll();

        if ($request->isMethod('POST')) {
            foreach ($permissions as $permission) {
                $allowed = '1' === $request->request->get('perm_'.$permission->getId());

                $rp = $em->getRepository(RolePermission::class)
                    ->findOneBy(['role' => $role, 'permission' => $permission]);

                if (!$rp) {
                    $rp = new RolePermission();
                    $rp->setRole($role);
                    $rp->setPermission($permission);
                    $em->persist($rp);
                }

                $rp->setAllowed($allowed);
            }

            $em->flush();

            return $this->redirectToRoute('admin_roles_permissions', ['id' => $role->getId()]);
        }

        return $this->render('admin/roles/permissions.html.twig', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Clear the current security token to force a permission/role refresh.
     */
    #[Route('/admin/refresh-permissions', name: 'admin_refresh_permissions')]
    #[Permission('admin.roles.refresh_permissions')]
    public function refreshPermissions(TokenStorageInterface $tokenStorage): Response
    {
        $tokenStorage->setToken(null);

        return $this->redirectToRoute('admin_roles');
    }
}
