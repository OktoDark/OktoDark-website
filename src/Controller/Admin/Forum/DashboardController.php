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

use App\Repository\ForumModerationLogRepository;
use App\Repository\ForumPostRepository;
use App\Repository\ForumThreadRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/forum')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private ForumThreadRepository $threadRepo,
        private ForumPostRepository $postRepo,
        private UserRepository $userRepo,
        private ForumModerationLogRepository $logRepo,
    ) {
    }

    #[Route('/dashboard', name: 'admin_forum_dashboard')]
    #[Route('', name: 'admin_forum_index')]
    public function index(): Response
    {
        return $this->render('@theme/admin/forum/dashboard.html.twig', [
            'stats' => [
                'total_threads' => $this->threadRepo->count([]),
                'total_posts' => $this->postRepo->count([]),
                'total_users' => $this->userRepo->count([]),
                'reported_posts' => $this->postRepo->count(['reported' => true]),
            ],
            'latest_threads' => $this->threadRepo->findBy([], ['createdAt' => 'DESC'], 5),
            'reported_items' => $this->postRepo->findBy(['reported' => true], ['createdAt' => 'DESC'], 5),
            'recent_logs' => $this->logRepo->findBy([], ['createdAt' => 'DESC'], 5),
        ]);
    }
}
