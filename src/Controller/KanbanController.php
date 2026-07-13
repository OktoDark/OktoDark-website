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

use App\Security\Attribute\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Permission('kanban.view', group: 'Kanban', label: 'View Kanban')]
class KanbanController extends AbstractController
{
    /**
     * Initializes the Kanban controller.
     */
    public function __construct()
    {
    }

    /**
     * Returns the Kanban API base response.
     */
    #[Route('/kanban/api', name: 'kanban_api_base', methods: ['GET'])]
    #[Permission('kanban.api', group: 'Kanban', label: 'Access Kanban API')]
    public function apiBase(): JsonResponse
    {
        return new JsonResponse(['message' => 'Kanban API Base']);
    }
}
