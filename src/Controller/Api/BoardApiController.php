<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Api;

use App\Entity\Board;
use App\Entity\User;
use App\Repository\BoardRepository;
use App\Repository\UserRepository;
use App\Service\BoardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/boards', name: 'api_board_')]
class BoardApiController extends AbstractController
{
    /**
     * Initialize board API dependencies for board, user, and entity manager operations.
     */
    public function __construct(
        private readonly BoardService $boardService,
        private readonly BoardRepository $boardRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * List all boards accessible to the current user.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listBoards(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        try {
            $boards = $this->boardRepository->findByUser($user);

            return $this->json([
                'success' => true,
                'boards' => array_map(fn (Board $board) => $this->getBoardData($board), $boards),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve boards');
        }
    }

    /**
     * Create a new board owned by the current user.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createBoard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['title'])) {
            return $this->handleBadRequest('Title is required');
        }

        try {
            $board = $this->boardService->createBoard(
                $data['title'],
                $data['description'] ?? null,
                $user,
                $data['isPublic'] ?? false
            );

            return $this->json([
                'success' => true,
                'board' => $this->getBoardData($board),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to create board');
        }
    }

    /**
     * Retrieve detailed board information including columns, cards, and members.
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getBoard(int $id): JsonResponse
    {
        try {
            $board = $this->boardRepository->findBoardWithDetails($id);

            if (!$board) {
                return $this->handleNotFound('Board');
            }

            $this->denyAccessUnlessGranted('board_view', $board);

            return $this->json([
                'success' => true,
                'board' => $this->getBoardDetailData($board),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve board details');
        }
    }

    /**
     * Update board metadata such as title, description, visibility, and background color.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateBoard(int $id, Request $request): JsonResponse
    {
        $board = $this->boardRepository->find($id);

        if (!$board) {
            return $this->handleNotFound('Board');
        }

        $this->denyAccessUnlessGranted('board_edit', $board);

        $data = json_decode($request->getContent(), true);

        try {
            $board = $this->boardService->updateBoard(
                $board,
                $data['title'] ?? $board->getTitle(),
                $data['description'] ?? $board->getDescription(),
                $data['isPublic'] ?? $board->isPublic(),
                $data['backgroundColor'] ?? null
            );

            return $this->json([
                'success' => true,
                'board' => $this->getBoardData($board),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to update board');
        }
    }

    /**
     * Delete a board after verifying the current user has delete permission.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteBoard(int $id): JsonResponse
    {
        $board = $this->boardRepository->find($id);

        if (!$board) {
            return $this->handleNotFound('Board');
        }

        $this->denyAccessUnlessGranted('board_delete', $board);

        try {
            $this->boardService->deleteBoard($board);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to delete board');
        }
    }

    /**
     * Add a member to a board by user ID after permission checks.
     */
    #[Route('/{id}/members', name: 'add_member', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addMember(int $id, Request $request): JsonResponse
    {
        $board = $this->boardRepository->find($id);

        if (!$board) {
            return $this->handleNotFound('Board');
        }

        $this->denyAccessUnlessGranted('board_manage_members', $board);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId'])) {
            return $this->handleBadRequest('User ID is required');
        }

        $member = $this->userRepository->find($data['userId']);

        if (!$member) {
            return $this->handleNotFound('User');
        }

        try {
            $this->boardService->addMember($board, $member);

            return $this->json([
                'success' => true,
                'board' => $this->getBoardData($board),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to add member to board');
        }
    }

    /**
     * Remove a member from a board after permission checks.
     */
    #[Route('/{id}/members/{userId}', name: 'remove_member', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeMember(int $id, int $userId): JsonResponse
    {
        $board = $this->boardRepository->find($id);

        if (!$board) {
            return $this->handleNotFound('Board');
        }

        $this->denyAccessUnlessGranted('board_manage_members', $board);

        $member = $this->userRepository->find($userId);

        if (!$member) {
            return $this->handleNotFound('User');
        }

        try {
            $this->boardService->removeMember($board, $member);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to remove member from board');
        }
    }

    private function getBoardData(Board $board): array
    {
        return [
            'id' => $board->getId(),
            'title' => $board->getTitle(),
            'description' => $board->getDescription(),
            'backgroundColor' => $board->getBackgroundColor(),
            'isPublic' => $board->isPublic(),
            'owner' => [
                'id' => $board->getOwner()->getId(),
                'username' => $board->getOwner()->getUsername(),
            ],
            'membersCount' => $board->getMembers()->count() + 1,
            'cardsCount' => $board->getCards()->count(),
            'columnsCount' => $board->getColumns()->count(),
            'createdAt' => $board->getCreatedAt()?->format(\DateTime::ATOM),
            'updatedAt' => $board->getUpdatedAt()?->format(\DateTime::ATOM),
        ];
    }

    private function getBoardDetailData(Board $board): array
    {
        return array_merge($this->getBoardData($board), [
            'columns' => array_map(static fn ($col) => [
                'id' => $col->getId(),
                'title' => $col->getTitle(),
                'position' => $col->getPosition(),
                'cards' => array_map(static fn ($card) => [
                    'id' => $card->getId(),
                    'title' => $card->getTitle(),
                    'position' => $card->getPosition(),
                    'priority' => $card->getPriority(),
                    'type' => $card->getType(),
                ], $col->getCards()->toArray()),
            ], $board->getColumns()->toArray()),
            'members' => array_map(static fn ($member) => [
                'id' => $member->getId(),
                'username' => $member->getUsername(),
                'firstName' => $member->getFirstName(),
                'lastName' => $member->getLastName(),
            ], array_merge($board->getMembers()->toArray(), [$board->getOwner()])),
        ]);
    }

    private function handleNotFound(string $entityName): JsonResponse
    {
        return $this->json(['success' => false, 'error' => $entityName.' not found'], Response::HTTP_NOT_FOUND);
    }

    private function handleBadRequest(string $message): JsonResponse
    {
        return $this->json(['success' => false, 'error' => $message], Response::HTTP_BAD_REQUEST);
    }

    private function handleServiceException(\Throwable $e, string $defaultMessage): JsonResponse
    {
        // Log the exception for debugging purposes
        // $this->logger->error($defaultMessage.': '.$e->getMessage());

        return $this->json(['success' => false, 'error' => $defaultMessage.': '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
