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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/forum/moderation-logs')]
#[IsGranted('ROLE_ADMIN')]
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
