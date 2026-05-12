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

use App\Entity\BoardColumn;
use App\Entity\Card;
use App\Entity\CardAssignee;
use App\Entity\CardComment;
use App\Entity\CardLabel;
use App\Entity\User;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class CardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CardRepository $cardRepository,
        private ActivityService $activityService,
        private KanbanBugSyncService $kanbanBugSyncService,
    ) {
    }

    public function createCard(
        BoardColumn $column,
        string $title,
        ?string $description = null,
        string $type = Card::TYPE_TASK,
        string $priority = Card::PRIORITY_MEDIUM,
        ?User $creator = null,
    ): Card {
        $card = new Card();
        $card->setColumn($column);
        $card->setBoard($column->getBoard());
        $card->setTitle($title);
        $card->setDescription($description);
        $card->setType($type);
        $card->setPriority($priority);
        $card->setPosition($this->cardRepository->getNextPosition($column));
        $card->setCreatedBy($creator);

        $this->entityManager->persist($card);
        $this->entityManager->flush();

        $this->activityService->logAction(
            $creator,
            'create',
            'Card',
            $card->getId(),
            "Created card: $title in {$column->getTitle()}"
        );

        return $card;
    }

    public function updateCard(Card $card, string $title, ?string $description = null, ?string $type = null, ?string $priority = null, ?\DateTimeImmutable $dueDate = null): Card
    {
        $changes = [];

        if ($card->getTitle() !== $title) {
            $changes['title'] = ['old' => $card->getTitle(), 'new' => $title];
            $card->setTitle($title);
        }

        if ($card->getDescription() !== $description) {
            $changes['description'] = ['old' => $card->getDescription(), 'new' => $description];
            $card->setDescription($description);
        }

        if ($type && $card->getType() !== $type) {
            $changes['type'] = ['old' => $card->getType(), 'new' => $type];
            $card->setType($type);
        }

        if ($priority && $card->getPriority() !== $priority) {
            $changes['priority'] = ['old' => $card->getPriority(), 'new' => $priority];
            $card->setPriority($priority);
        }

        if (null !== $dueDate && $card->getDueDate() !== $dueDate) {
            $changes['dueDate'] = ['old' => $card->getDueDate()?->format('Y-m-d'), 'new' => $dueDate->format('Y-m-d')];
            $card->setDueDate($dueDate);
        }

        $card->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        if (!empty($changes)) {
            $this->activityService->logAction(
                $card->getCreatedBy(),
                'update',
                'Card',
                $card->getId(),
                "Updated card: {$card->getTitle()}",
                $changes
            );
        }

        return $card;
    }

    public function moveCard(Card $card, BoardColumn $newColumn, int $newPosition, ?User $user = null): Card
    {
        // Check if moving to Done/Completed column with unresolved bugs
        if ($this->isDoneColumn($newColumn) && $this->kanbanBugSyncService->hasUnresolvedBugs($card)) {
            throw new \InvalidArgumentException('Cannot move card to Done/Completed column while it has unresolved bugs.');
        }

        $oldColumn = $card->getColumn();
        $card->setColumn($newColumn);
        $card->setPosition($newPosition);

        $this->entityManager->flush();

        $this->activityService->logAction(
            $card->getCreatedBy(),
            'move',
            'Card',
            $card->getId(),
            "Moved card from {$oldColumn->getTitle()} to {$newColumn->getTitle()}",
            ['column' => ['old' => $oldColumn->getId(), 'new' => $newColumn->getId()]]
        );

        return $card;
    }

    private function isDoneColumn(BoardColumn $column): bool
    {
        $title = strtolower($column->getTitle());

        return in_array($title, ['done', 'completed', 'closed']);
    }

    public function deleteCard(Card $card): void
    {
        $cardId = $card->getId();
        $cardTitle = $card->getTitle();

        $this->entityManager->remove($card);
        $this->entityManager->flush();

        $this->activityService->logAction(
            $card->getCreatedBy(),
            'delete',
            'Card',
            $cardId,
            "Deleted card: $cardTitle"
        );
    }

    public function assignUser(Card $card, User $user, string $role = CardAssignee::ROLE_ASSIGNEE): CardAssignee
    {
        $assignee = new CardAssignee();
        $assignee->setCard($card);
        $assignee->setAssignee($user);
        $assignee->setRole($role);

        $card->addAssignee($assignee);
        $this->entityManager->persist($assignee);
        $this->entityManager->flush();

        $this->activityService->logAction(
            null,
            'assign',
            'Card',
            $card->getId(),
            "Assigned {$user->getUsername()} to card: {$card->getTitle()}"
        );

        return $assignee;
    }

    public function unassignUser(Card $card, User $user): void
    {
        $assignees = $card->getAssignees();

        foreach ($assignees as $assignee) {
            if ($assignee->getAssignee() === $user) {
                $card->removeAssignee($assignee);
                $this->entityManager->remove($assignee);
            }
        }

        $this->entityManager->flush();

        $this->activityService->logAction(
            null,
            'update',
            'Card',
            $card->getId(),
            "Unassigned {$user->getUsername()} from card: {$card->getTitle()}"
        );
    }

    public function addLabel(Card $card, CardLabel $label): Card
    {
        if (!$card->getLabels()->contains($label)) {
            $card->addLabel($label);
            $this->entityManager->flush();

            $this->activityService->logAction(
                null,
                'update',
                'Card',
                $card->getId(),
                "Added label '{$label->getName()}' to card: {$card->getTitle()}"
            );
        }

        return $card;
    }

    public function removeLabel(Card $card, CardLabel $label): Card
    {
        $card->removeLabel($label);
        $this->entityManager->flush();

        $this->activityService->logAction(
            null,
            'update',
            'Card',
            $card->getId(),
            "Removed label '{$label->getName()}' from card: {$card->getTitle()}"
        );

        return $card;
    }

    public function addComment(Card $card, User $author, string $content): CardComment
    {
        $comment = new CardComment();
        $comment->setCard($card);
        $comment->setAuthor($author);
        $comment->setContent($content);

        $card->addComment($comment);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->activityService->logAction(
            $author,
            'comment',
            'Card',
            $card->getId(),
            "Commented on card: {$card->getTitle()}"
        );

        return $comment;
    }

    public function updateComment(CardComment $comment, string $content): CardComment
    {
        $comment->setContent($content);
        $comment->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $comment;
    }

    public function deleteComment(CardComment $comment): void
    {
        $card = $comment->getCard();
        $card->removeComment($comment);
        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        $this->activityService->logAction(
            $comment->getAuthor(),
            'delete',
            'CardComment',
            $comment->getId(),
            "Deleted comment from card: {$card->getTitle()}"
        );
    }
}
