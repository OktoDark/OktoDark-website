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

use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Service\EmailIdentityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contact')]
#[IsGranted('ROLE_ADMIN')]
class AdminContactController extends AbstractController
{
    #[Route('/', name: 'admin_contact_index', methods: ['GET'])]
    public function index(ContactRepository $repo): Response
    {
        return $this->render('@theme/admin/contact/index.html.twig', [
            'messages' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}', name: 'admin_contact_show', methods: ['GET', 'POST'])]
    public function show(
        Contact $message,
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        EmailIdentityService $emailIdentity,
    ): Response {
        // Mark as read when opened
        if (!$message->isRead()) {
            $message->setIsRead(true);
            $em->flush();
        }

        if ($request->isMethod('POST')) {
            $replyText = $request->request->get('reply_message');
            $token = $request->request->get('csrf_token');

            if (!$this->isCsrfTokenValid('admin_reply_contact', $token)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            if ($replyText) {
                // 1. Update Database
                $message->setReplyMessage($replyText);
                $message->setRepliedAt(new \DateTime());
                $em->flush();

                // 2. Send Real Email to User
                $email = (new Email())
                    ->from($emailIdentity->contact()) // <-- from service
                    ->to($message->getEmail())
                    ->subject('RE: '.$message->getSubject())
                    ->text($replyText);

                // Optional: route through a specific mailer transport
                $email->getHeaders()->addTextHeader('X-Transport', 'contact');

                $mailer->send($email);

                $this->addFlash('success', 'Reply sent and saved!');

                return $this->redirectToRoute('admin_contact_index');
            }
        }

        return $this->render('@theme/admin/contact/show.html.twig', [
            'message' => $message,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_contact_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Contact $contact,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$contact->getId(), $request->request->get('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();
            $this->addFlash('success', 'Message deleted successfully.');
        }

        return $this->redirectToRoute('admin_contact_index');
    }
}
