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

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class ActivityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityLogRepository $activityLogRepository
    ) {
    }

    public function logAction(
        ?User $user,
        string $action,
        string $entityType,
        int $entityId,
        ?string $description = null,
        ?array $changes = null
    ): ActivityLog {
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);
        $log->setChanges($changes);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    public function getEntityActivity(string $entityType, int $entityId, int $limit = 50): array
    {
        return $this->activityLogRepository->findByEntity($entityType, $entityId);
    }

    public function getRecentActivity(int $limit = 50): array
    {
        return $this->activityLogRepository->findRecent($limit);
    }

    public function clearOldActivity(int $daysOld = 90): int
    {
        $date = new \DateTimeImmutable("-$daysOld days");

        $count = $this->entityManager->createQuery(
            'DELETE FROM App\Entity\ActivityLog a WHERE a.createdAt < :date'
        )
            ->setParameter('date', $date)
            ->execute()
        ;

        return $count;
    }
}

