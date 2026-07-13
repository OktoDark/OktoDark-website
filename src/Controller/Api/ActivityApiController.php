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

use App\Entity\ActivityLog;
use App\Repository\ActivityLogRepository;
use App\Service\ActivityService;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/activity', name: 'api_activity_')]
class ActivityApiController extends AbstractController
{
    /**
     * Initialize activity API dependencies for log retrieval and entity activity aggregation.
     */
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly ActivityLogRepository $activityRepository,
    ) {
    }

    /**
     * Retrieve activity logs for a specific entity identified by type and ID.
     */
    #[Route('/{entityType}/{entityId}', name: 'get_entity', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getEntityActivity(string $entityType, int $entityId): JsonResponse
    {
        try {
            $activity = $this->activityService->getEntityActivity($entityType, $entityId);

            return $this->json([
                'success' => true,
                'activity' => array_map(fn (ActivityLog $log) => $this->getActivityData($log), $activity),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve activity for entity');
        }
    }

    /**
     * Retrieve the 50 most recent activity logs across all entities.
     */
    #[Route('/recent', name: 'recent', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getRecentActivity(): JsonResponse
    {
        try {
            $activity = $this->activityService->getRecentActivity(50);

            return $this->json([
                'success' => true,
                'activity' => array_map(fn (ActivityLog $log) => $this->getActivityData($log), $activity),
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve recent activity');
        }
    }

    private function getActivityData(ActivityLog $log): array
    {
        return [
            'id' => $log->getId(),
            'user' => $log->getUser() ? [
                'id' => $log->getUser()->getId(),
                'username' => $log->getUser()->getUsername(),
            ] : null,
            'action' => $log->getAction(),
            'entityType' => $log->getEntityType(),
            'entityId' => $log->getEntityId(),
            'description' => $log->getDescription(),
            'changes' => $log->getChanges(),
            'createdAt' => $log->getCreatedAt()?->format(\DateTime::ATOM),
        ];
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

        $message = $defaultMessage;
        if ($e instanceof ORMException) {
            $message .= ': Database ORM error: '.$e->getMessage();
        } elseif ($e instanceof DBALException) {
            $message .= ': Database connection/query error: '.$e->getMessage();
        } else {
            $message .= ': '.$e->getMessage();
        }

        return $this->json(['success' => false, 'error' => $message], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
