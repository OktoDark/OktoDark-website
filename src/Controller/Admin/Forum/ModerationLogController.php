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
use App\Security\Attribute\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/forum/moderation-logs')]
#[Permission('admin.forum.moderation-logs.index', group: 'Admin', label: 'View forum moderation logs')]
final class ModerationLogController extends AbstractController
{
    public function __construct(
        private ForumModerationLogRepository $logRepo,
    ) {
    }

    #[Route('/', name: 'admin_forum_moderation_logs')]
    public function index(): Response
    {
        return $this->render('@theme/admin/forum/moderation_logs.html.twig', [
            'logs' => $this->logRepo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }
}
