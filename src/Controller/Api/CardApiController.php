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

use App\Entity\Card;
use App\Entity\User;
use App\Repository\BoardColumnRepository;
use App\Repository\CardLabelRepository;
use App\Repository\CardRepository;
use App\Repository\UserRepository;
use App\Service\CardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cards', name: 'api_card_')]
class CardApiController extends AbstractController
{
    /**
     * Initialize card API dependencies for card operations, labels, columns, and user lookups.
     */
    public function __construct(
        private readonly CardService $cardService,
        private readonly CardRepository $cardRepository,
        private readonly CardLabelRepository $labelRepository,
        private readonly BoardColumnRepository $columnRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Create a new card within a specific column.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['columnId']) || !isset($data['title'])) {
            return $this->handleBadRequest('Column ID and title are required');
        }

        $column = $this->columnRepository->find($data['columnId']);

        if (!$column) {
            return $this->handleNotFound('Column');
        }

        $this->denyAccessUnlessGranted('card_view', $column->getBoard());

        try {
            $card = $this->cardService->createCard(
                $column,
                $data['title'],
                $data['description'] ?? null,
                $data['type'] ?? Card::TYPE_TASK,
                $data['priority'] ?? Card::PRIORITY_MEDIUM,
                $user
            );

            return $this->json([
                'success' => true,
                'card' => $this->getCardData($card),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to create card');
        }
    }

    /**
     * Retrieve detailed card information including assignees, labels, comments count, and linked bug.
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getCard(int $id): JsonResponse
    {
        $card = $this->cardRepository->findCardWithDetails($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_view', $card);

        return $this->json([
            'success' => true,
            'card' => $this->getCardDetailData($card),
        ]);
    }

    /**
     * Update card fields including title, description, type, priority, and due date.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateCard(int $id, Request $request): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_edit', $card);

        $data = json_decode($request->getContent(), true);

        $dueDate = null;
        if (isset($data['dueDate'])) {
            try {
                $dueDate = new \DateTimeImmutable($data['dueDate']);
            } catch (\Exception $e) {
                return $this->handleBadRequest('Invalid date format for dueDate');
            }
        }

        try {
            $card = $this->cardService->updateCard(
                $card,
                $data['title'] ?? $card->getTitle(),
                $data['description'] ?? $card->getDescription(),
                $data['type'] ?? null,
                $data['priority'] ?? null,
                $dueDate
            );

            return $this->json([
                'success' => true,
                'card' => $this->getCardData($card),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to update card');
        }
    }

    /**
     * Delete a card after verifying delete permission.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteCard(int $id): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_delete', $card);

        try {
            $this->cardService->deleteCard($card);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to delete card');
        }
    }

    /**
     * Move a card to a different column and position.
     */
    #[Route('/{id}/move', name: 'move', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function moveCard(int $id, Request $request): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_edit', $card);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['columnId']) || !isset($data['position'])) {
            return $this->handleBadRequest('Column ID and position are required');
        }

        $column = $this->columnRepository->find($data['columnId']);

        if (!$column) {
            return $this->handleNotFound('Column');
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $card = $this->cardService->moveCard($card, $column, $data['position'], $user);

            return $this->json([
                'success' => true,
                'card' => $this->getCardData($card),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to move card');
        }
    }

    /**
     * Assign a user to a card with an optional role.
     */
    #[Route('/{id}/assign', name: 'assign', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function assignUser(int $id, Request $request): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_assign', $card);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId'])) {
            return $this->handleBadRequest('User ID is required');
        }

        $user = $this->userRepository->find($data['userId']);

        if (!$user) {
            return $this->handleNotFound('User');
        }

        try {
            $this->cardService->assignUser($card, $user, $data['role'] ?? 'assignee');

            return $this->json([
                'success' => true,
                'card' => $this->getCardData($card),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to assign user to card');
        }
    }

    /**
     * Remove a user assignment from a card.
     */
    #[Route('/{id}/assign/{userId}', name: 'unassign', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function unassignUser(int $id, int $userId): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_assign', $card);

        $user = $this->userRepository->find($userId);

        if (!$user) {
            return $this->handleNotFound('User');
        }

        try {
            $this->cardService->unassignUser($card, $user);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to unassign user from card');
        }
    }

    /**
     * Attach an existing label to a card.
     */
    #[Route('/{id}/labels', name: 'add_label', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addLabel(int $id, Request $request): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_edit', $card);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['labelId'])) {
            return $this->handleBadRequest('Label ID is required');
        }

        $label = $this->labelRepository->find($data['labelId']);

        if (!$label) {
            return $this->handleNotFound('Label');
        }

        try {
            $this->cardService->addLabel($card, $label);

            return $this->json(['success' => true, 'card' => $this->getCardData($card)]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to add label to card');
        }
    }

    /**
     * Remove a label from a card.
     */
    #[Route('/{id}/labels/{labelId}', name: 'remove_label', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeLabel(int $id, int $labelId): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_edit', $card);

        $label = $this->labelRepository->find($labelId);

        if (!$label) {
            return $this->handleNotFound('Label');
        }

        try {
            $this->cardService->removeLabel($card, $label);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to remove label from card');
        }
    }

    /**
     * Retrieve all bugs linked to a specific card.
     */
    #[Route('/{id}/bugs', name: 'get_bugs', methods: ['GET'])]
    public function getCardBugs(int $id): JsonResponse
    {
        $card = $this->cardRepository->find($id);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        $this->denyAccessUnlessGranted('card_view', $card);

        try {
            $bugs = $card->getBugs();

            return $this->json([
                'success' => true,
                'bugs' => array_map(static fn ($bug) => [
                    'id' => $bug->getId(),
                    'title' => $bug->getTitle(),
                    'status' => $bug->getStatus(),
                    'severity' => $bug->getSeverity(),
                    'reporter' => [
                        'id' => $bug->getReporter()?->getId(),
                        'username' => $bug->getReporter()?->getUsername(),
                    ],
                    'assignee' => $bug->getAssignee() ? [
                        'id' => $bug->getAssignee()->getId(),
                        'username' => $bug->getAssignee()->getUsername(),
                    ] : null,
                ], $bugs->toArray()),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to get card bugs');
        }
    }

    private function getCardData(Card $card): array
    {
        return [
            'id' => $card->getId(),
            'title' => $card->getTitle(),
            'description' => $card->getDescription(),
            'position' => $card->getPosition(),
            'type' => $card->getType(),
            'priority' => $card->getPriority(),
            'dueDate' => $card->getDueDate()?->format('Y-m-d'),
            'columnId' => $card->getColumn()?->getId(),
            'boardId' => $card->getBoard()?->getId(),
            'createdAt' => $card->getCreatedAt()?->format(\DateTime::ATOM),
            'updatedAt' => $card->getUpdatedAt()?->format(\DateTime::ATOM),
        ];
    }

    private function getCardDetailData(Card $card): array
    {
        return array_merge($this->getCardData($card), [
            'assignees' => array_map(static fn ($assignee) => [
                'id' => $assignee->getAssignee()->getId(),
                'username' => $assignee->getAssignee()->getUsername(),
                'role' => $assignee->getRole(),
            ], $card->getAssignees()->toArray()),
            'labels' => array_map(static fn ($label) => [
                'id' => $label->getId(),
                'name' => $label->getName(),
                'color' => $label->getColor(),
            ], $card->getLabels()->toArray()),
            'commentsCount' => $card->getComments()->count(),
            'hasBug' => null !== $card->getBug(),
            'bugId' => $card->getBug()?->getId(),
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
