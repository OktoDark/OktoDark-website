<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
class NotificationController extends AbstractController
{
    /**
     * Initializes the notification controller.
     */
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Displays the notifications listing for the authenticated user.
     */
    #[Route('/', name: 'notifications_index', methods: ['GET'])]
    #[Permission('notification.view')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Fetch all notifications for the user, ordered by creation date
        $notifications = $this->em->getRepository(Notification::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('@theme/notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Marks all notifications as read for the authenticated user.
     */
    #[Route('/mark-all-read', name: 'notifications_mark_all_read', methods: ['POST'])]
    #[Permission('notification.mark_all_read')]
    public function markAllAsRead(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Mark all unread notifications for the user as read
        $unreadNotifications = $this->em->getRepository(Notification::class)->findBy(
            ['user' => $user, 'isRead' => false]
        );

        foreach ($unreadNotifications as $notification) {
            $notification->markAsRead();
        }
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'unreadCount' => 0]);
        }

        $this->addFlash('success', 'All notifications marked as read.');

        return $this->redirectToRoute('notifications_index');
    }

    /**
     * Marks a single notification as read for the authenticated user.
     */
    #[Route('/{id}/mark-read', name: 'notifications_mark_as_read', methods: ['POST'])]
    #[Permission('notification.mark_read')]
    public function markAsRead(Notification $notification, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Ensure the notification belongs to the current user
        if ($notification->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $notification->markAsRead();
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true, 'unreadCount' => $user->getUnreadNotificationsCount()]);
        }

        $this->addFlash('success', 'Notification marked as read.');

        // Redirect to the notification's link if available, otherwise to the index
        return $this->redirect($notification->getLink() ?? $this->generateUrl('notifications_index'));
    }

    /**
     * Returns the latest unread notifications for the authenticated user.
     */
    #[Route('/api/latest', name: 'notifications_fetch_latest', methods: ['GET'])]
    #[Permission('notification.fetch_latest')]
    public function fetchLatest(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $unreadCount = $user->getUnreadNotificationsCount();
        $latestNotifications = $this->em->getRepository(Notification::class)->findBy(
            ['user' => $user, 'isRead' => false],
            ['createdAt' => 'DESC'],
            5 // Limit to 5 latest unread notifications
        );

        $notificationsData = [];
        foreach ($latestNotifications as $notification) {
            $notificationsData[] = [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'link' => $notification->getLink(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
                'isRead' => $notification->isRead(),
            ];
        }

        return new JsonResponse([
            'unreadCount' => $unreadCount,
            'notifications' => $notificationsData,
        ]);
    }
}
