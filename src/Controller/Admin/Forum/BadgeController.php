<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin\Forum;

use App\Entity\Badge;
use App\Repository\BadgeRepository;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/forum/badges')]
final class BadgeController extends AbstractController
{
    /**
     * Initialize repositories and entity manager for badge CRUD operations.
     */
    public function __construct(
        private BadgeRepository $repo,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * List all badges and handle badge creation, update, or deletion from the same form.
     */
    #[Route('', name: 'admin_badges')]
    #[Permission('admin.forum.badges.index')]
    public function index(Request $request): Response
    {
        // Handle CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $badge = $id ? $this->repo->find($id) : new Badge();

            if (!$badge && $id) {
                throw $this->createNotFoundException('Badge not found.');
            }

            $badge->setName($request->request->get('name'));
            $badge->setIcon($request->request->get('icon'));
            $badge->setDescription($request->request->get('description'));
            $badge->setColor($request->request->get('color') ?: '#16a34a');

            $badge->setActionType($request->request->get('actionType') ?: null);
            $badge->setThreshold('' !== $request->request->get('threshold') ? (int) $request->request->get('threshold') : null);
            $badge->setIsPermanent((bool) $request->request->get('isPermanent'));

            $this->em->persist($badge);
            $this->em->flush();

            $this->addFlash('success', $id ? 'Badge updated.' : 'Badge created.');

            return $this->redirectToRoute('admin_badges');
        }

        // Handle DELETE
        if ($id = $request->query->get('delete')) {
            $badge = $this->repo->find($id);
            if ($badge) {
                $this->em->remove($badge);
                $this->em->flush();
                $this->addFlash('success', 'Badge deleted.');
            }

            return $this->redirectToRoute('admin_badges');
        }

        return $this->render('@theme/admin/forum/badges.html.twig', [
            'badges' => $this->repo->findAll(),
        ]);
    }
}
