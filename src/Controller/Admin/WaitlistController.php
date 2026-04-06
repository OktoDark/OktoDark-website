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

use App\Entity\RegistrationWaitlist;
use App\Repository\RegistrationWaitlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class WaitlistController extends AbstractController
{
    public function __construct(
        private RegistrationWaitlistRepository $waitlistRepo,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/waitlist', name: 'admin_waitlist_index')]
    public function index(): Response
    {
        $entries = $this->waitlistRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('@theme/admin/waitlist.html.twig', [
            'entries' => $entries,
        ]);
    }

    #[Route('/admin/waitlist/delete/{id}', name: 'admin_waitlist_delete', methods: ['POST'])]
    public function delete(RegistrationWaitlist $entry): RedirectResponse
    {
        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', 'Waitlist entry removed.');

        return $this->redirectToRoute('admin_waitlist_index');
    }
}
