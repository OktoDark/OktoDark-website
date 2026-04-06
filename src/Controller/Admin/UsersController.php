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

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
class UsersController extends AbstractController
{
    #[Route('', name: 'admin_users')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(User::class);

        // CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $user = $id ? $repo->find($id) : new User();

            $user->setActive('1' === $request->request->get('active'));
            $user->setUsername($request->request->get('username'));
            $user->setFullName($request->request->get('fullName'));
            $user->setEmail($request->request->get('email'));

            // Roles (array)
            $roles = $request->request->all('roles') ?? [];
            $user->setRoles($roles);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $id ? 'User updated.' : 'User created.');

            return $this->redirectToRoute('admin_users');
        }

        // DELETE
        if ($request->query->get('delete')) {
            $user = $repo->find($request->query->get('delete'));
            if ($user) {
                $em->remove($user);
                $em->flush();
                $this->addFlash('success', 'User deleted.');
            }

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('@theme/admin/users.html.twig', [
            'users' => $repo->findAll(),
        ]);
    }
}
