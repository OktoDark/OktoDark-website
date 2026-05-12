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
use App\Entity\User;
use App\Repository\BoardRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class BoardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BoardRepository $boardRepository,
        private ActivityService $activityService,
    ) {
    }

    public function createBoard(string $title, ?string $description, User $owner, bool $isPublic = false): Board
    {
        $board = new Board();
        $board->setTitle($title);
        $board->setDescription($description);
        $board->setOwner($owner);
        $board->setIsPublic($isPublic);

        $this->entityManager->persist($board);
        $this->entityManager->flush();

        // Log activity
        $this->activityService->logAction(
            $owner,
            'create',
            'Board',
            $board->getId(),
            "Created board: $title"
        );

        return $board;
    }

    public function updateBoard(Board $board, string $title, ?string $description, ?bool $isPublic = null, ?string $backgroundColor = null): Board
    {
        $changes = [];

        if ($board->getTitle() !== $title) {
            $changes['title'] = ['old' => $board->getTitle(), 'new' => $title];
            $board->setTitle($title);
        }

        if ($board->getDescription() !== $description) {
            $changes['description'] = ['old' => $board->getDescription(), 'new' => $description];
            $board->setDescription($description);
        }

        if (null !== $isPublic && $board->isPublic() !== $isPublic) {
            $changes['isPublic'] = ['old' => $board->isPublic(), 'new' => $isPublic];
            $board->setIsPublic($isPublic);
        }

        if ($backgroundColor && $board->getBackgroundColor() !== $backgroundColor) {
            $changes['backgroundColor'] = ['old' => $board->getBackgroundColor(), 'new' => $backgroundColor];
            $board->setBackgroundColor($backgroundColor);
        }

        $board->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        if (!empty($changes)) {
            $this->activityService->logAction(
                $board->getOwner(),
                'update',
                'Board',
                $board->getId(),
                'Updated board',
                $changes
            );
        }

        return $board;
    }

    public function deleteBoard(Board $board): void
    {
        $owner = $board->getOwner();
        $boardId = $board->getId();

        $this->entityManager->remove($board);
        $this->entityManager->flush();

        if ($owner) {
            $this->activityService->logAction(
                $owner,
                'delete',
                'Board',
                $boardId,
                "Deleted board: {$board->getTitle()}"
            );
        }
    }

    public function addMember(Board $board, User $member): Board
    {
        if (!$board->isMember($member)) {
            $board->addMember($member);
            $this->entityManager->flush();

            $this->activityService->logAction(
                $board->getOwner(),
                'update',
                'Board',
                $board->getId(),
                "Added member: {$member->getUsername()}"
            );
        }

        return $board;
    }

    public function removeMember(Board $board, User $member): Board
    {
        $board->removeMember($member);
        $this->entityManager->flush();

        $this->activityService->logAction(
            $board->getOwner(),
            'update',
            'Board',
            $board->getId(),
            "Removed member: {$member->getUsername()}"
        );

        return $board;
    }

    public function getUserBoards(User $user): array
    {
        return $this->boardRepository->findByUser($user);
    }

    public function getBoardMembers(Board $board): array
    {
        $members = $board->getMembers()->toArray();
        array_unshift($members, $board->getOwner());

        return array_unique($members, SORT_REGULAR);
    }
}
