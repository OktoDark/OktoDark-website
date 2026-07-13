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
use App\Security\Attribute\Permission;
use App\Service\EmailIdentityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/contact')]
#[Permission('admin.contact.index', group: 'Admin', label: 'View Contact')]
class AdminContactController extends AbstractController
{
    /**
     * Lists contact messages with search, sorting and pagination.
     *
     * Reads "search", "sort", "order" and "page" query parameters, maps the
     * public sort keys to entity columns and delegates filtering/pagination to
     * the ContactRepository search helpers.
     */
    #[Route('/', name: 'admin_contact_index', methods: ['GET'])]
    public function index(Request $request, ContactRepository $repo): Response
    {
        // 1. Read query params
        $search = $request->query->get('search');
        $sort = $request->query->get('sort', 'date');
        $order = $request->query->get('order', 'desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        // 2. Build sorting map
        $sortMap = [
            'date' => 'createdAt',
            'sender' => 'name',
            'subject' => 'subject',
        ];

        $sortField = $sortMap[$sort] ?? 'createdAt';

        // 3. Fetch total count (with search)
        $total = $repo->countSearch($search);

        // 4. Fetch paginated results (with search)
        $messages = $repo->searchMessages(
            $search,
            $sortField,
            $order,
            $limit,
            ($page - 1) * $limit
        );

        // 5. Compute last page
        $lastPage = max(1, (int) ceil($total / $limit));

        return $this->render('@theme/admin/contact/index.html.twig', [
            'messages' => $messages,
            'current_page' => $page,
            'last_page' => $lastPage,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    /**
     * Displays a contact message and handles the admin reply form.
     *
     * Marks the message as read on first view, then on POST validates the CSRF
     * token and, when a reply body is provided, persists the reply on the
     * entity, sends a reply email via the configured contact transport and
     * redirects back to the listing.
     */
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

    /**
     * Deletes a contact message after CSRF token validation.
     *
     * Removes the entity only when the "delete{id}" CSRF token matches the
     * submitted "_token", then redirects to the contact listing.
     */
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
