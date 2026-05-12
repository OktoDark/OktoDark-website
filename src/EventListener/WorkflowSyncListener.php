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

use App\Entity\ActivityLog;
use App\Entity\Bug;
use App\Entity\Card;
use App\Entity\User;
use App\Service\KanbanBugSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;

class WorkflowSyncListener
{
    private KanbanBugSyncService $syncService;
    private EntityManagerInterface $entityManager;

    public function __construct(KanbanBugSyncService $syncService, EntityManagerInterface $entityManager)
    {
        $this->syncService = $syncService;
        $this->entityManager = $entityManager;
    }

    /**
     * Listen for Card updates (column changes).
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Card) {
            $this->handleCardUpdate($entity, $args);
        } elseif ($entity instanceof Bug) {
            $this->handleBugUpdate($entity, $args);
        }
    }

    private function handleCardUpdate(Card $card, PostUpdateEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changes = $uow->getEntityChangeSet($card);

        // Check if the column changed
        if (isset($changes['column'])) {
            $oldColumn = $changes['column'][0];
            $newColumn = $changes['column'][1];

            if ($oldColumn && $newColumn && $oldColumn->getId() !== $newColumn->getId()) {
                // Log the card movement
                $this->logWorkflowActivity(
                    'Card',
                    $card->getId(),
                    "Card moved from '{$oldColumn->getTitle()}' to '{$newColumn->getTitle()}' column",
                    $card->getCreatedBy()
                );

                // Sync bugs and log the sync
                $this->syncService->syncBugsOnCardMove($card);

                // Check if card was moved due to bug resolution and notify
                if ('review' === strtolower($newColumn->getTitle()) || 'done' === strtolower($newColumn->getTitle())) {
                    $this->syncService->notifyCardMovedToReview($card);
                }

                // Log bug status changes
                foreach ($card->getBugs() as $bug) {
                    $this->logWorkflowActivity(
                        'Bug',
                        $bug->getId(),
                        "Bug status synced to '{$bug->getStatus()}' due to card movement to '{$newColumn->getTitle()}'",
                        $bug->getReporter()
                    );
                }
            }
        }
    }

    private function handleBugUpdate(Bug $bug, PostUpdateEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changes = $uow->getEntityChangeSet($bug);

        // Check if the status changed
        if (isset($changes['status'])) {
            $oldStatus = $changes['status'][0];
            $newStatus = $changes['status'][1];

            if ($oldStatus !== $newStatus) {
                // Check if this was triggered by workflow sync
                $this->syncService->syncCardOnBugResolution($bug);

                // Log bug status change
                $this->logWorkflowActivity(
                    'Bug',
                    $bug->getId(),
                    "Bug status changed from '{$oldStatus}' to '{$newStatus}'",
                    $bug->getReporter()
                );

                // Check if card was moved due to bug resolution
                if ($bug->getKanbanCard()) {
                    $card = $bug->getKanbanCard();
                    $this->logWorkflowActivity(
                        'Card',
                        $card->getId(),
                        "Card moved to '{$card->getColumn()->getTitle()}' due to all bugs being resolved",
                        $card->getCreatedBy()
                    );
                }
            }
        }

        // Check if the bug was linked to a card
        if (isset($changes['kanbanCard'])) {
            $oldCard = $changes['kanbanCard'][0];
            $newCard = $changes['kanbanCard'][1];

            if (!$oldCard && $newCard) {
                // Bug was linked to a card
                $this->syncService->autoAssignBugAssignees($bug);
                $this->syncService->notifyBugAddedToCard($bug);

                $this->logWorkflowActivity(
                    'Bug',
                    $bug->getId(),
                    "Bug linked to card '{$newCard->getTitle()}'",
                    $bug->getReporter()
                );
            }
        }
    }

    private function logWorkflowActivity(string $entityType, int $entityId, string $description, ?User $user): void
    {
        $log = new ActivityLog();
        $log->setAction(ActivityLog::ACTION_UPDATE);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);
        $log->setUser($user);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
