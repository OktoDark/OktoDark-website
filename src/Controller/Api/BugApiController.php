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

use App\Entity\Bug;
use App\Entity\User;
use App\Repository\BugRepository;
use App\Repository\CardRepository;
use App\Repository\UserRepository;
use App\Service\BugService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/bugs', name: 'api_bug_')]
class BugApiController extends AbstractController
{
    /**
     * Initialize bug API dependencies for bug operations, repositories, and validation.
     */
    public function __construct(
        private readonly BugService $bugService,
        private readonly BugRepository $bugRepository,
        private readonly CardRepository $cardRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * List bugs filtered by status, severity, or assignee, falling back to unresolved bugs.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listBugs(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $severity = $request->query->get('severity');
        $assigneeId = $request->query->get('assigneeId');

        $bugs = [];

        if ($status) {
            $bugs = $this->bugRepository->findByStatus($status);
        } elseif ($severity) {
            $bugs = $this->bugRepository->findBySeverity($severity);
        } elseif ($assigneeId) {
            $assignee = $this->userRepository->find($assigneeId);
            if ($assignee) {
                $bugs = $this->bugRepository->findByAssignee($assignee);
            }
        } else {
            $bugs = $this->bugRepository->findUnresolved();
        }

        return $this->json([
            'success' => true,
            'bugs' => array_map(fn (Bug $bug) => $this->getBugData($bug), $bugs),
        ]);
    }

    /**
     * Create a new bug report with validation and optional reproduction details.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createBug(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['title']) || empty($data['title'])) {
            return $this->handleBadRequest('Title is required');
        }

        try {
            $bug = $this->bugService->createBug(
                $data['title'],
                $data['description'] ?? null,
                $user,
                $data['severity'] ?? Bug::SEVERITY_MEDIUM,
                $data['reproduction_steps'] ?? null,
                $data['expected_result'] ?? null,
                $data['actual_result'] ?? null,
                $data['operatingSystem'] ?? null,
                $data['operatingSystemVersion'] ?? null
            );

            // Validate the created bug entity
            $errors = $this->validator->validate($bug);
            if (\count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath().': '.$error->getMessage();
                }

                return $this->json(
                    ['success' => false, 'error' => 'Validation failed', 'errors' => $errorMessages],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return $this->json([
                'success' => true,
                'bug' => $this->getBugData($bug),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to create bug');
        }
    }

    /**
     * Retrieve detailed bug information including description and reproduction details.
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getBug(int $id): JsonResponse
    {
        $bug = $this->bugRepository->findBugWithDetails($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        return $this->json([
            'success' => true,
            'bug' => $this->getBugDetailData($bug),
        ]);
    }

    /**
     * Update bug fields from the provided JSON payload.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateBug(int $id, Request $request): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        $data = json_decode($request->getContent(), true);

        try {
            $bug = $this->bugService->updateBug($bug, $data);

            return $this->json([
                'success' => true,
                'bug' => $this->getBugData($bug),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to update bug');
        }
    }

    /**
     * Change the status of a bug.
     */
    #[Route('/{id}/status', name: 'change_status', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function changeStatus(int $id, Request $request): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return $this->handleBadRequest('Status is required');
        }

        try {
            $bug = $this->bugService->changeStatus($bug, $data['status']);

            return $this->json([
                'success' => true,
                'bug' => $this->getBugData($bug),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to change bug status');
        }
    }

    /**
     * Change the severity of a bug.
     */
    #[Route('/{id}/severity', name: 'change_severity', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function changeSeverity(int $id, Request $request): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['severity'])) {
            return $this->handleBadRequest('Severity is required');
        }

        try {
            $bug = $this->bugService->changeSeverity($bug, $data['severity']);

            return $this->json([
                'success' => true,
                'bug' => $this->getBugData($bug),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to change bug severity');
        }
    }

    /**
     * Assign a bug to a specific user.
     */
    #[Route('/{id}/assign', name: 'assign', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function assignBug(int $id, Request $request): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId'])) {
            return $this->handleBadRequest('User ID is required');
        }

        $assignee = $this->userRepository->find($data['userId']);

        if (!$assignee) {
            return $this->handleNotFound('User');
        }

        try {
            $bug = $this->bugService->assignBug($bug, $assignee);

            return $this->json([
                'success' => true,
                'bug' => $this->getBugData($bug),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to assign bug');
        }
    }

    /**
     * Unassign the current assignee from a bug.
     */
    #[Route('/{id}/unassign', name: 'unassign', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unassignBug(int $id): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        try {
            $bug = $this->bugService->unassignBug($bug);

            return $this->json(['success' => true, 'bug' => $this->getBugData($bug)]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to unassign bug');
        }
    }

    /**
     * Link a bug to a card for traceability.
     */
    #[Route('/{id}/link-card', name: 'link_card', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function linkCard(int $id, Request $request): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['cardId'])) {
            return $this->handleBadRequest('Card ID is required');
        }

        $card = $this->cardRepository->find($data['cardId']);

        if (!$card) {
            return $this->handleNotFound('Card');
        }

        try {
            $bug = $this->bugService->linkCard($bug, $card);

            return $this->json([
                'success' => true,
                'bug' => $this->getBugData($bug),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to link card to bug');
        }
    }

    /**
     * Remove the card link from a bug.
     */
    #[Route('/{id}/unlink-card', name: 'unlink_card', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function unlinkCard(int $id): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        try {
            $bug = $this->bugService->unlinkCard($bug);

            return $this->json(['success' => true, 'bug' => $this->getBugData($bug)]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to unlink card from bug');
        }
    }

    /**
     * Permanently delete a bug.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteBug(int $id): JsonResponse
    {
        $bug = $this->bugRepository->find($id);

        if (!$bug) {
            return $this->handleNotFound('Bug');
        }

        try {
            $this->bugService->deleteBug($bug);

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to delete bug');
        }
    }

    private function getBugData(Bug $bug): array
    {
        return [
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
            'cardId' => $bug->getCard()?->getId(),
            'reportedAt' => $bug->getReportedAt()?->format(\DateTime::ATOM),
            'resolvedAt' => $bug->getResolvedAt()?->format(\DateTime::ATOM),
            'updatedAt' => $bug->getUpdatedAt()?->format(\DateTime::ATOM),
        ];
    }

    private function getBugDetailData(Bug $bug): array
    {
        return array_merge($this->getBugData($bug), [
            'description' => $bug->getDescription(),
            'reproduction_steps' => $bug->getReproductionSteps(),
            'expected_result' => $bug->getExpectedResult(),
            'actual_result' => $bug->getActualResult(),
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
