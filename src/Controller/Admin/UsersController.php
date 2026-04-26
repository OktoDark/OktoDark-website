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
use App\Repository\BadgeRepository;
use App\Service\TrustedDeviceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
class UsersController extends AbstractController
{
    #[Route('', name: 'admin_users')]
    public function index(Request $request, EntityManagerInterface $em, BadgeRepository $badgeRepo): Response
    {
        $userRepo = $em->getRepository(User::class);

        // CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $user = $id ? $userRepo->find($id) : new User();

            if (!$user) {
                $this->addFlash('error', 'User not found.');

                return $this->redirectToRoute('admin_users');
            }

            $user->setActive('1' === $request->request->get('active'));
            $user->setUsername($request->request->get('username'));
            $user->setFirstName($request->request->get('firstName'));
            $user->setLastName($request->request->get('lastName'));
            $user->setEmail($request->request->get('email'));
            $user->setIsVerified((bool) $request->request->get('isVerified'));

            // Roles (array)
            $roles = $request->request->all('roles') ?? [];
            $user->setRoles($roles);

            // Badges (array)
            $selectedBadgeIds = $request->request->all('badges') ?? [];
            $currentBadges = $user->getBadges()->map(static fn ($badge) => $badge->getId())->toArray();

            // Remove badges no longer selected
            foreach ($currentBadges as $badgeId) {
                if (!\in_array($badgeId, $selectedBadgeIds, true)) {
                    $badgeToRemove = $badgeRepo->find($badgeId);
                    if ($badgeToRemove) {
                        $user->getBadges()->removeElement($badgeToRemove);
                    }
                }
            }

            // Add newly selected badges
            foreach ($selectedBadgeIds as $badgeId) {
                if (!\in_array($badgeId, $currentBadges, true)) {
                    $badgeToAdd = $badgeRepo->find($badgeId);
                    if ($badgeToAdd) {
                        $user->addBadge($badgeToAdd);
                    }
                }
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $id ? 'User updated.' : 'User created.');

            return $this->redirectToRoute('admin_users');
        }

        // DELETE
        if ($request->query->get('delete')) {
            $user = $userRepo->find($request->query->get('delete'));
            if ($user) {
                $em->remove($user);
                $em->flush();
                $this->addFlash('success', 'User deleted.');
            }

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('@theme/admin/users.html.twig', [
            'users' => $userRepo->findAll(),
            'allBadges' => $badgeRepo->findAll(),
        ]);
    }

    #[Route('/{id}/view', name: 'admin_user_view')]
    public function view(User $user): Response
    {
        return $this->render('@theme/admin/user_view.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/trusted-devices/clear', name: 'admin_clear_trusted_devices')]
    public function adminClearTrustedDevices(
        User $user,
        TrustedDeviceService $service,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $service->removeAllForUser($user);

        $this->addFlash('success', 'All trusted devices removed for this user.');

        return $this->redirectToRoute('admin_user_view', ['id' => $user->getId()]);
    }
}
