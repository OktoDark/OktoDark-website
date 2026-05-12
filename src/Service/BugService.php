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

use App\Entity\Bug;
use App\Entity\Card;
use App\Entity\User;
use App\Repository\BugRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class BugService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BugRepository $bugRepository,
        private ActivityService $activityService,
        private KanbanBugSyncService $kanbanBugSyncService, // Inject KanbanBugSyncService
    ) {
    }

    public function createBug(
        string $title,
        ?string $description,
        User $reporter,
        string $severity = Bug::SEVERITY_MEDIUM,
        ?string $reproductionSteps = null,
        ?string $expectedResult = null,
        ?string $actualResult = null,
        ?string $operatingSystem = null,
        ?string $operatingSystemVersion = null,
    ): Bug {
        $bug = new Bug();
        $bug->setTitle($title);
        $bug->setDescription($description);
        $bug->setReporter($reporter);
        $bug->setSeverity($severity);
        $bug->setReproductionSteps($reproductionSteps);
        $bug->setExpectedResult($expectedResult);
        $bug->setActualResult($actualResult);
        $bug->setOperatingSystem($operatingSystem);
        $bug->setOperatingSystemVersion($operatingSystemVersion);

        $this->entityManager->persist($bug);
        $this->entityManager->flush();

        $this->activityService->logAction(
            $reporter,
            'create',
            'Bug',
            $bug->getId(),
            "Created bug report: $title"
        );

        // Automatically create a Kanban card if the bug is critical
        if (Bug::SEVERITY_CRITICAL === $bug->getSeverity()) {
            $this->kanbanBugSyncService->autoCreateCardForCriticalBug($bug);
        }

        return $bug;
    }

    public function updateBug(Bug $bug, array $data): Bug
    {
        $changes = [];

        if (isset($data['title']) && $bug->getTitle() !== $data['title']) {
            $changes['title'] = ['old' => $bug->getTitle(), 'new' => $data['title']];
            $bug->setTitle($data['title']);
        }

        if (isset($data['description']) && $bug->getDescription() !== $data['description']) {
            $changes['description'] = ['old' => $bug->getDescription(), 'new' => $data['description']];
            $bug->setDescription($data['description']);
        }

        // Handle severity change within updateBug if it's not handled by changeSeverity
        $oldSeverity = $bug->getSeverity();
        if (isset($data['severity']) && $oldSeverity !== $data['severity']) {
            $changes['severity'] = ['old' => $oldSeverity, 'new' => $data['severity']];
            $bug->setSeverity($data['severity']);
            // If severity changes to critical and no card exists, create one
            if (Bug::SEVERITY_CRITICAL === $bug->getSeverity() && null === $bug->getKanbanCard()) {
                $this->kanbanBugSyncService->autoCreateCardForCriticalBug($bug);
            }
        }

        if (isset($data['reproduction_steps']) && $bug->getReproductionSteps() !== $data['reproduction_steps']) {
            $changes['reproduction_steps'] = ['old' => $bug->getReproductionSteps(), 'new' => $data['reproduction_steps']];
            $bug->setReproductionSteps($data['reproduction_steps']);
        }

        if (isset($data['expected_result']) && $bug->getExpectedResult() !== $data['expected_result']) {
            $changes['expected_result'] = ['old' => $bug->getExpectedResult(), 'new' => $data['expected_result']];
            $bug->setExpectedResult($data['expected_result']);
        }

        if (isset($data['actual_result']) && $bug->getActualResult() !== $data['actual_result']) {
            $changes['actual_result'] = ['old' => $bug->getActualResult(), 'new' => $data['actual_result']];
            $bug->setActualResult($data['actual_result']);
        }

        $bug->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        if (!empty($changes)) {
            $this->activityService->logAction(
                $bug->getReporter(),
                'update',
                'Bug',
                $bug->getId(),
                "Updated bug: {$bug->getTitle()}",
                $changes
            );
        }

        return $bug;
    }

    public function changeStatus(Bug $bug, string $status): Bug
    {
        $oldStatus = $bug->getStatus();
        $bug->setStatus($status);

        if (Bug::STATUS_RESOLVED === $status && null === $bug->getResolvedAt()) {
            $bug->setResolvedAt(new \DateTimeImmutable());
        }

        $bug->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->activityService->logAction(
            null,
            'update',
            'Bug',
            $bug->getId(),
            "Changed bug status from $oldStatus to $status",
            ['status' => ['old' => $oldStatus, 'new' => $status]]
        );

        return $bug;
    }

    public function changeSeverity(Bug $bug, string $severity): Bug
    {
        $oldSeverity = $bug->getSeverity();
        $bug->setSeverity($severity);
        $bug->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->activityService->logAction(
            null,
            'update',
            'Bug',
            $bug->getId(),
            "Changed bug severity from $oldSeverity to $severity",
            ['severity' => ['old' => $oldSeverity, 'new' => $severity]]
        );

        // If severity changes to critical and no card exists, create one
        if (Bug::SEVERITY_CRITICAL === $bug->getSeverity() && null === $bug->getKanbanCard()) {
            $this->kanbanBugSyncService->autoCreateCardForCriticalBug($bug);
        }

        return $bug;
    }

    public function assignBug(Bug $bug, User $assignee): Bug
    {
        $oldAssignee = $bug->getAssignee();
        $bug->setAssignee($assignee);
        $this->entityManager->flush();

        $this->activityService->logAction(
            null,
            'assign',
            'Bug',
            $bug->getId(),
            "Assigned bug to {$assignee->getUsername()}",
            ['assignee' => ['old' => $oldAssignee?->getUsername(), 'new' => $assignee->getUsername()]]
        );

        return $bug;
    }

    public function unassignBug(Bug $bug): Bug
    {
        $oldAssignee = $bug->getAssignee();
        $bug->setAssignee(null);
        $this->entityManager->flush();

        if ($oldAssignee) {
            $this->activityService->logAction(
                null,
                'update',
                'Bug',
                $bug->getId(),
                "Unassigned {$oldAssignee->getUsername()} from bug"
            );
        }

        return $bug;
    }

    public function linkCard(Bug $bug, Card $card): Bug
    {
        $oldCard = $bug->getKanbanCard(); // Use getKanbanCard() as per entity
        $bug->setKanbanCard($card); // Use setKanbanCard() as per entity
        // The Card entity should manage its collection of bugs, not a single bug property
        // $card->setBug($bug); // This line is likely incorrect if Card has ManyToMany or OneToMany with Bug
        $this->entityManager->flush();

        $this->activityService->logAction(
            null,
            'update',
            'Bug',
            $bug->getId(),
            "Linked bug to card: {$card->getTitle()}"
        );

        return $bug;
    }

    public function unlinkCard(Bug $bug): Bug
    {
        if (null !== $bug->getKanbanCard()) { // Use getKanbanCard()
            $card = $bug->getKanbanCard();
            $bug->setKanbanCard(null); // Use setKanbanCard()
            // The Card entity should manage its collection of bugs, not a single bug property
            // $card->setBug(null); // This line is likely incorrect if Card has ManyToMany or OneToMany with Bug
            $this->entityManager->flush();

            $this->activityService->logAction(
                null,
                'update',
                'Bug',
                $bug->getId(),
                "Unlinked bug from card: {$card->getTitle()}"
            );
        }

        return $bug;
    }

    public function deleteBug(Bug $bug): void
    {
        $bugId = $bug->getId();
        $bugTitle = $bug->getTitle();

        $this->entityManager->remove($bug);
        $this->entityManager->flush();

        $this->activityService->logAction(
            $bug->getReporter(),
            'delete',
            'Bug',
            $bugId,
            "Deleted bug: $bugTitle"
        );
    }

    public function getCriticalBugs(): array
    {
        return $this->bugRepository->findCritical();
    }

    public function getUnresolvedBugs(): array
    {
        return $this->bugRepository->findUnresolved();
    }
}
