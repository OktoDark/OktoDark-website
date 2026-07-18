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

use App\Entity\PermissionGroup;
use App\Form\Admin\PermissionGroupType;
use App\Repository\PermissionGroupRepository;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/permission-groups')]
class PermissionGroupController extends AbstractController
{
    /**
     * List all permission groups.
     */
    #[Route('/', name: 'admin_permission_groups')]
    #[Permission('admin.permission_groups.index')]
    public function index(PermissionGroupRepository $repo): Response
    {
        return $this->render('@theme/admin/permission_groups/index.html.twig', [
            'groups' => $repo->findAll(),
        ]);
    }

    /**
     * Create a new permission group via the dedicated form type.
     */
    #[Route('/create', name: 'admin_permission_groups_create')]
    #[Permission('admin.permission_groups.create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $group = new PermissionGroup();
        $form = $this->createForm(PermissionGroupType::class, $group);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($group);
            $em->flush();

            return $this->redirectToRoute('admin_permission_groups');
        }

        return $this->render('@theme/admin/permission_groups/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Create Permission Group',
        ]);
    }

    /**
     * Edit an existing permission group via the dedicated form type.
     */
    #[Route('/{id}/edit', name: 'admin_permission_groups_edit')]
    #[Permission('admin.permission_groups.edit')]
    public function edit(PermissionGroup $group, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PermissionGroupType::class, $group);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('admin_permission_groups');
        }

        return $this->render('admin/permission_groups/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit Permission Group',
        ]);
    }
}
