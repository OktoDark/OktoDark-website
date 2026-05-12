<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\EventListener;

use App\Entity\Bug;
use App\Service\KanbanBugSyncService;
use Doctrine\ORM\Event\PostPersistEventArgs;

class BugAutomationListener
{
    private KanbanBugSyncService $syncService;

    public function __construct(KanbanBugSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Listen for Bug creation.
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Bug) {
            $this->handleBugCreation($entity);
        }
    }

    private function handleBugCreation(Bug $bug): void
    {
        // Auto-create card for critical bugs
        $createdCard = $this->syncService->autoCreateCardForCriticalBug($bug);
        if ($createdCard) {
            // Card was created, no need for further automation
            return;
        }

        // If bug is linked to a card, auto-assign assignees and notify
        if ($bug->getKanbanCard()) {
            $this->syncService->autoAssignBugAssignees($bug);
            $this->syncService->notifyBugAddedToCard($bug);
        }
    }
}
