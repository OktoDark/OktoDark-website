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
use App\Security\Attribute\Permission;
use App\Service\InviteMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WaitlistController extends AbstractController
{
    /**
     * Initialize controller dependencies for the waitlist, persistence and invites.
     */
    public function __construct(
        private RegistrationWaitlistRepository $waitlistRepo,
        private EntityManagerInterface $em,
        private InviteMailer $inviteMailer,
    ) {
    }

    /**
     * List registration waitlist entries ordered by most recent submission.
     */
    #[Route('/admin/waitlist', name: 'admin_waitlist_index')]
    #[Permission('admin.waitlist.index')]
    public function index(): Response
    {
        $entries = $this->waitlistRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('@theme/admin/waitlist.html.twig', [
            'entries' => $entries,
        ]);
    }

    /**
     * Send an invite email to a waitlist entry and remove it from the list.
     */
    #[Route('/admin/waitlist/invite/{id}', name: 'admin_waitlist_invite', methods: ['POST'])]
    public function invite(RegistrationWaitlist $entry): RedirectResponse
    {
        $this->inviteMailer->sendInvite($entry->getEmail());

        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', 'Invite sent and waitlist entry removed.');

        return $this->redirectToRoute('admin_waitlist_index');
    }

    /**
     * Delete a waitlist entry without sending an invite.
     */
    #[Route('/admin/waitlist/delete/{id}', name: 'admin_waitlist_delete', methods: ['POST'])]
    public function delete(RegistrationWaitlist $entry): RedirectResponse
    {
        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', 'Waitlist entry removed.');

        return $this->redirectToRoute('admin_waitlist_index');
    }
}
