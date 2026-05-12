<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\Bug;
use App\Entity\Card;
use App\Entity\User;
use App\Repository\BoardRepository;
use App\Repository\BugRepository;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;

class KanbanBugSyncService
{
    private EntityManagerInterface $entityManager;
    private BugRepository $bugRepository;
    private BoardRepository $boardRepository;
    private CardRepository $cardRepository;
    private NotificationService $notificationService;

    // Status mapping between Kanban columns and Bug statuses
    private const STATUS_MAPPING = [
        'To Do' => Bug::STATUS_OPEN,
        'In Progress' => Bug::STATUS_IN_PROGRESS,
        'Review' => Bug::STATUS_RESOLVED,
        'Done' => Bug::STATUS_RESOLVED,
        'Backlog' => Bug::STATUS_OPEN,
        'Testing' => Bug::STATUS_IN_PROGRESS,
        'Completed' => Bug::STATUS_RESOLVED,
        'Closed' => Bug::STATUS_CLOSED,
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        BugRepository $bugRepository,
        BoardRepository $boardRepository,
        CardRepository $cardRepository,
        NotificationService $notificationService,
    ) {
        $this->entityManager = $entityManager;
        $this->bugRepository = $bugRepository;
        $this->boardRepository = $boardRepository;
        $this->cardRepository = $cardRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Sync bug statuses when a card moves to a new column.
     */
    public function syncBugsOnCardMove(Card $card): void
    {
        $columnTitle = $card->getColumn()->getTitle();
        $newBugStatus = $this->getBugStatusForColumn($columnTitle);

        if (null === $newBugStatus) {
            // No mapping found for this column, skip sync
            return;
        }

        foreach ($card->getBugs() as $bug) {
            if ($bug->getStatus() !== $newBugStatus) {
                $oldStatus = $bug->getStatus();
                $bug->setStatus($newBugStatus);

                // Set resolved date if moving to resolved/closed status
                if (\in_array($newBugStatus, [Bug::STATUS_RESOLVED, Bug::STATUS_CLOSED], true) && null === $bug->getResolvedAt()) {
                    $bug->setResolvedAt(new \DateTimeImmutable());
                }

                $this->entityManager->persist($bug);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Optionally move card to Review when all bugs are resolved.
     */
    public function syncCardOnBugResolution(Bug $bug): void
    {
        if (null === $bug->getKanbanCard()) {
            return;
        }

        $card = $bug->getKanbanCard();

        // Check if all bugs on this card are resolved
        $allBugsResolved = true;
        foreach ($card->getBugs() as $cardBug) {
            if (!\in_array($cardBug->getStatus(), [Bug::STATUS_RESOLVED, Bug::STATUS_CLOSED, Bug::STATUS_WONT_FIX], true)) {
                $allBugsResolved = false;
                break;
            }
        }

        if ($allBugsResolved) {
            // Try to move card to "Review" or "Done" column if it exists
            $board = $card->getBoard();
            $reviewColumn = null;
            $doneColumn = null;

            foreach ($board->getColumns() as $column) {
                if ('review' === mb_strtolower($column->getTitle())) {
                    $reviewColumn = $column;
                } elseif ('done' === mb_strtolower($column->getTitle())) {
                    $doneColumn = $column;
                }
            }

            // Prefer Review column, fallback to Done
            $targetColumn = $reviewColumn ?? $doneColumn;

            if ($targetColumn && $targetColumn !== $card->getColumn()) {
                $card->setColumn($targetColumn);
                $this->entityManager->persist($card);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * Get the appropriate bug status for a given column title.
     */
    private function getBugStatusForColumn(string $columnTitle): ?string
    {
        $normalizedTitle = mb_strtolower(mb_trim($columnTitle));

        // Direct mapping
        if (isset(self::STATUS_MAPPING[$columnTitle])) {
            return self::STATUS_MAPPING[$columnTitle];
        }

        // Case-insensitive mapping
        foreach (self::STATUS_MAPPING as $kanbanStatus => $bugStatus) {
            if (mb_strtolower($kanbanStatus) === $normalizedTitle) {
                return $bugStatus;
            }
        }

        return null;
    }

    /**
     * Check if a card has unresolved bugs.
     */
    public function hasUnresolvedBugs(Card $card): bool
    {
        foreach ($card->getBugs() as $bug) {
            if (!\in_array($bug->getStatus(), [Bug::STATUS_RESOLVED, Bug::STATUS_CLOSED, Bug::STATUS_WONT_FIX], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get count of unresolved bugs for a card.
     */
    public function getUnresolvedBugCount(Card $card): int
    {
        $count = 0;
        foreach ($card->getBugs() as $bug) {
            if (!\in_array($bug->getStatus(), [Bug::STATUS_RESOLVED, Bug::STATUS_CLOSED, Bug::STATUS_WONT_FIX], true)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Auto-create a Kanban card when a bug is critical.
     */
    public function autoCreateCardForCriticalBug(Bug $bug): ?Card
    {
        if (Bug::SEVERITY_CRITICAL !== $bug->getSeverity()) {
            return null;
        }

        // Find a default board for critical bugs (e.g., first board owned by admin or something)
        // For now, let's assume we have a "Bug Tracker" board or create one
        $board = $this->findOrCreateBugBoard($bug->getReporter());

        if (!$board) {
            return null;
        }

        // Find the "To Do" column
        $todoColumn = null;
        foreach ($board->getColumns() as $column) {
            if ('to do' === mb_strtolower($column->getTitle()) || 'backlog' === mb_strtolower($column->getTitle())) {
                $todoColumn = $column;
                break;
            }
        }

        if (!$todoColumn) {
            // Create a default "To Do" column if it doesn't exist
            $todoColumn = new BoardColumn();
            $todoColumn->setBoard($board);
            $todoColumn->setTitle('To Do');
            $todoColumn->setPosition(0);
            $this->entityManager->persist($todoColumn);
        }

        // Create the card
        $card = new Card();
        $card->setBoard($board);
        $card->setColumn($todoColumn);
        $card->setTitle('Critical Bug: '.$bug->getTitle());
        $card->setDescription($bug->getDescription());
        $card->setType(Card::TYPE_BUG);
        $card->setPriority(Card::PRIORITY_CRITICAL);
        $card->setCreatedBy($bug->getReporter());

        // Link the bug to the card
        $card->addBug($bug);

        $this->entityManager->persist($card);
        $this->entityManager->flush();

        return $card;
    }

    /**
     * Find or create a bug tracking board.
     */
    private function findOrCreateBugBoard(User $user): ?Board
    {
        // Try to find an existing "Bug Tracker" board
        $boards = $this->boardRepository->findBy(['owner' => $user]);
        foreach ($boards as $board) {
            if ('bug tracker' === mb_strtolower($board->getTitle())) {
                return $board;
            }
        }

        // Create a new "Bug Tracker" board
        $board = new Board();
        $board->setTitle('Bug Tracker');
        $board->setDescription('Automatically created board for critical bugs');
        $board->setOwner($user);

        // Create default columns
        $columns = ['Backlog', 'To Do', 'In Progress', 'Review', 'Done'];
        foreach ($columns as $index => $columnTitle) {
            $column = new BoardColumn();
            $column->setBoard($board);
            $column->setTitle($columnTitle);
            $column->setPosition($index);
            $board->addColumn($column);
        }

        $this->entityManager->persist($board);
        $this->entityManager->flush();

        return $board;
    }

    /**
     * Auto-assign developers to bugs based on card assignees.
     */
    public function autoAssignBugAssignees(Bug $bug): void
    {
        $card = $bug->getKanbanCard();
        if (!$card) {
            return;
        }

        foreach ($card->getAssignees() as $cardAssignee) {
            $user = $cardAssignee->getUser();
            if ($user && !$bug->getAssignee()) {
                // Assign to the first assignee if bug has no assignee
                $bug->setAssignee($user);
                break;
            }
        }

        if ($bug->getAssignee()) {
            $this->entityManager->persist($bug);
            $this->entityManager->flush();
        }
    }

    /**
     * Send notifications for bug-related events.
     */
    public function notifyBugAddedToCard(Bug $bug): void
    {
        $card = $bug->getKanbanCard();
        if (!$card) {
            return;
        }

        // Notify card assignees
        foreach ($card->getAssignees() as $assignee) {
            $user = $assignee->getUser();
            if ($user && $user !== $bug->getReporter()) {
                $this->notificationService->notify(
                    $user,
                    'New Bug Added to Your Card',
                    "Bug #{$bug->getId()}: {$bug->getTitle()} has been added to card '{$card->getTitle()}'.",
                    "/kanban/card/{$card->getId()}"
                );
            }
        }

        // Notify card creator if different from reporter
        $creator = $card->getCreatedBy();
        if ($creator && $creator !== $bug->getReporter() && !$card->getAssignees()->exists(static fn ($key, $a) => $a->getUser() === $creator)) {
            $this->notificationService->notify(
                $creator,
                'New Bug Added to Your Card',
                "Bug #{$bug->getId()}: {$bug->getTitle()} has been added to your card '{$card->getTitle()}'.",
                "/kanban/card/{$card->getId()}"
            );
        }
    }

    /**
     * Notify when card is moved to Review because all bugs resolved.
     */
    public function notifyCardMovedToReview(Card $card): void
    {
        // Notify card assignees
        foreach ($card->getAssignees() as $assignee) {
            $user = $assignee->getUser();
            if ($user) {
                $this->notificationService->notify(
                    $user,
                    'Card Moved to Review',
                    "Card '{$card->getTitle()}' has been moved to Review because all linked bugs are resolved.",
                    "/kanban/card/{$card->getId()}"
                );
            }
        }

        // Notify card creator
        $creator = $card->getCreatedBy();
        if ($creator && !$card->getAssignees()->exists(static fn ($key, $a) => $a->getUser() === $creator)) {
            $this->notificationService->notify(
                $creator,
                'Card Moved to Review',
                "Your card '{$card->getTitle()}' has been moved to Review because all linked bugs are resolved.",
                "/kanban/card/{$card->getId()}"
            );
        }
    }

    /**
     * Notify when trying to close card with unresolved bugs.
     */
    public function notifyCannotCloseCardWithBugs(Card $card, User $user): void
    {
        $unresolvedCount = $this->getUnresolvedBugCount($card);

        $this->notificationService->notify(
            $user,
            'Cannot Close Card',
            "Card '{$card->getTitle()}' cannot be closed because it has {$unresolvedCount} unresolved bug(s).",
            "/kanban/card/{$card->getId()}"
        );
    }
}
