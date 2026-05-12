<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller;

use App\Entity\Bug;
use App\Entity\OurGames;
use App\Entity\User;
use App\Form\BugFormType;
use App\Repository\BoardRepository;
use App\Repository\BugRepository;
use App\Repository\OurGamesRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BugTrackerController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly BugRepository $bugRepository,
        private readonly BoardRepository $boardRepository,
        private readonly OurGamesRepository $ourGamesRepository,
    ) {
    }

    #[Route('/workspace/bugs', name: 'kanban_bugs')]
    #[Route('/workspace/{shortNameSlug}/bugs', name: 'kanban_bugs_project', requirements: ['shortNameSlug' => '[a-zA-Z0-9_-]+'])]
    public function bugTracker(#[MapEntity(mapping: ['shortNameSlug' => 'shortNameSlug'])] ?OurGames $game = null): Response
    {
        $users = $this->userRepository->findAll();
        $teamData = [];
        foreach ($users as $user) {
            $teamData[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
            ];
        }

        $filters = [];
        $board = null;

        if ($game) {
            $filters['ourGame'] = $game;
            // If the game has a default bug board, we might want to pre-select it or use it for filtering
            // For now, we'll just pass it to the template if it exists
            $board = $game->getBugLink();
        } else {
            $filters['ourGame'] = null;
        }

        $bugs = $this->bugRepository->findFiltered($filters);

        // Initialize Bug entity with game context if on a project-specific page
        $newBug = new Bug();
        if ($game) {
            $newBug->setOurGame($game);
        }
        $bugForm = $this->createForm(BugFormType::class, $newBug);

        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        /** @var User $user */
        $user = $authResult;

        // Find boards relevant to the current context (general or specific game)
        $boardFilters = ['member' => $user];
        if ($game) {
            $boardFilters['ourGame'] = $game;
        } else {
            $boardFilters['ourGame'] = null;
        }
        $availableBoards = $this->boardRepository->findFiltered($boardFilters);

        return $this->render('modern/kanban/bug-tracker.html.twig', [
            'pageTitle' => $game ? 'Bug Tracker for '.$game->getName() : 'Bug Tracker',
            'team' => $teamData,
            'bugForm' => $bugForm->createView(),
            'bugs' => $bugs,
            'game' => $game, // Pass game to Twig
            'board' => $board, // Pass the default bug board if available
            'availableBoards' => $availableBoards,
        ]);
    }

    #[Route('/workspace/bugs/details/{id}', name: 'kanban_bug_details', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/workspace/{shortNameSlug}/bugs/details/{id}', name: 'kanban_bug_details_project', requirements: ['shortNameSlug' => '[a-zA-Z0-9_-]+', 'id' => '\d+'], methods: ['GET'])]
    public function viewBugDetails(Bug $bug, #[MapEntity(mapping: ['shortNameSlug' => 'shortNameSlug'])] ?OurGames $game = null): Response
    {
        if ($game && $bug->getOurGame() !== $game) {
            throw $this->createNotFoundException('Bug not found for this project.');
        } elseif (!$game && null !== $bug->getOurGame()) {
            throw $this->createNotFoundException('Bug not found for this context.');
        }

        return $this->render('modern/kanban/bug-details.html.twig', [
            'bug' => $bug,
            'pageTitle' => 'Bug #'.$bug->getId().': '.$bug->getTitle(),
            'game' => $game, // Pass game to Twig
        ]);
    }

    #[Route('/kanban/api/bugs/{id}', name: 'kanban_api_bug_get', methods: ['GET'])]
    public function getBugApi(Bug $bug): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }

        return new JsonResponse([
            'success' => true,
            'bug' => [
                'id' => $bug->getId(),
                'title' => $bug->getTitle(),
                'description' => $bug->getDescription(),
                'status' => $bug->getStatus(),
                'severity' => $bug->getSeverity(),
                'reproductionSteps' => $bug->getReproductionSteps(),
                'expectedResult' => $bug->getExpectedResult(),
                'actualResult' => $bug->getActualResult(),
                'operatingSystem' => $bug->getOperatingSystem(),
                'operatingSystemVersion' => $bug->getOperatingSystemVersion(),
                'reporter' => $bug->getReporter()?->getUsername(),
                'assignee' => $bug->getAssignee()?->getUsername(),
                'assigneeId' => $bug->getAssignee()?->getId(),
                'reportedAt' => $bug->getReportedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $bug->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
                'ourGameSlug' => $bug->getOurGame()?->getShortNameSlug(),
            ],
        ]);
    }

    #[Route('/kanban/api/bugs/{id}/assign', name: 'kanban_api_bug_assign', methods: ['PATCH'])]
    public function assignBugApi(Request $request, Bug $bug): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        // No need to cast $currentUser here, as it's not directly used after authentication check

        $data = json_decode($request->getContent(), true);
        $assigneeId = $data['assigneeId'] ?? null;

        $assignee = null;
        if ($assigneeId) {
            $assignee = $this->userRepository->find($assigneeId);
            if (!$assignee) {
                return new JsonResponse(['success' => false, 'message' => 'Assignee user not found'], Response::HTTP_NOT_FOUND);
            }
        }

        $bug->setAssignee($assignee);
        $bug->setUpdatedAt(new \DateTimeImmutable());

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Bug assignee updated successfully!',
            'bug' => [
                'id' => $bug->getId(),
                'assignee' => $bug->getAssignee()?->getUsername(),
                'assigneeId' => $bug->getAssignee()?->getId(),
            ],
        ]);
    }

    #[Route('/kanban/api/bugs/{id}/status', name: 'kanban_api_bug_status_change', methods: ['PATCH'])]
    public function changeBugStatusApi(Request $request, Bug $bug): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        // No need to cast $currentUser here, as it's not directly used after authentication check

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            return new JsonResponse(['success' => false, 'message' => 'New status is required'], Response::HTTP_BAD_REQUEST);
        }

        $bug->setStatus($newStatus);
        $bug->setUpdatedAt(new \DateTimeImmutable());

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Bug status updated successfully!',
            'status' => $bug->getStatus(),
            'bugId' => $bug->getId(),
        ]);
    }

    #[Route('/kanban/api/bugs', name: 'kanban_api_bugs_create', methods: ['POST'])]
    public function createBugApi(Request $request): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        /** @var User $user */
        $user = $authResult;

        $bug = new Bug();
        $bug->setReporter($user);

        $form = $this->createForm(BugFormType::class, $bug);

        $data = json_decode($request->getContent(), true);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Manually handle ourGame association
                $ourGameId = $data['ourGame'] ?? null;
                if ($ourGameId) {
                    // Convert to int if it's a string
                    $ourGameId = (int) $ourGameId;
                    $ourGame = $this->ourGamesRepository->find($ourGameId);
                    if ($ourGame) {
                        $bug->setOurGame($ourGame);
                    } else {
                        return new JsonResponse(['success' => false, 'message' => 'Selected game not found'], Response::HTTP_BAD_REQUEST);
                    }
                }
                // If no ourGame provided, it remains null , which is correct

                $this->entityManager->persist($bug);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                return $this->handleDatabaseException($e);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Bug reported successfully!',
                'bug' => [
                    'id' => $bug->getId(),
                    'title' => $bug->getTitle(),
                    'description' => $bug->getDescription(),
                    'status' => $bug->getStatus(),
                    'severity' => $bug->getSeverity(),
                    'operatingSystem' => $bug->getOperatingSystem(),
                    'operatingSystemVersion' => $bug->getOperatingSystemVersion(),
                    'reporter' => $bug->getReporter()?->getUsername(),
                    'assignee' => $bug->getAssignee()?->getUsername(),
                    'kanbanCard' => $bug->getKanbanCard() ? [
                        'id' => $bug->getKanbanCard()->getId(),
                        'title' => $bug->getKanbanCard()->getTitle(),
                    ] : null,
                    'ourGameSlug' => $bug->getOurGame()?->getShortNameSlug(),
                ],
            ], Response::HTTP_CREATED);
        }

        $errors = $this->getFormErrors($form);

        return new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/kanban/api/bugs', name: 'kanban_api_bugs_list', methods: ['GET'])]
    public function getBugsApi(Request $request): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        /** @var User $user */
        $user = $authResult;

        $filters = [];
        $status = $request->query->get('status');
        $severity = $request->query->get('severity');
        $assigneeId = $request->query->get('assigneeId');
        $boardId = $request->query->get('boardId');
        $cardFilter = $request->query->get('cardFilter');
        $gameSlug = $request->query->get('gameSlug');

        if ($gameSlug) {
            $ourGame = $this->ourGamesRepository->findOneBy(['shortNameSlug' => $gameSlug]);
            if (!$ourGame) {
                return new JsonResponse(['success' => false, 'message' => 'Project not found'], Response::HTTP_NOT_FOUND);
            }
            $filters['ourGame'] = $ourGame;
        } else {
            $filters['ourGame'] = null; // Only show bugs not linked to any game
        }

        if ($status) {
            $filters['status'] = $status;
        }
        if ($severity) {
            $filters['severity'] = $severity;
        }
        if ($assigneeId) {
            $assignee = $this->userRepository->find($assigneeId);
            if ($assignee) {
                $filters['assignee'] = $assignee;
            }
        }
        if ($boardId) {
            $board = $this->boardRepository->find($boardId);
            if ($board) {
                // $this->denyAccessUnlessGranted('board_view', $board); // This might be needed if bugs can only be viewed by board members
                $filters['board'] = $board;
            }
        }
        if ($cardFilter) {
            $filters['cardFilter'] = 'true' === $cardFilter;
        }

        $bugs = $this->bugRepository->findFiltered($filters);

        $bugsData = [];
        foreach ($bugs as $bug) {
            $bugsData[] = [
                'id' => $bug->getId(),
                'title' => $bug->getTitle(),
                'description' => $bug->getDescription(),
                'status' => $bug->getStatus(),
                'severity' => $bug->getSeverity(),
                'operatingSystem' => $bug->getOperatingSystem(),
                'operatingSystemVersion' => $bug->getOperatingSystemVersion(),
                'reporter' => $bug->getReporter()?->getUsername(),
                'assignee' => $bug->getAssignee()?->getUsername(),
                'reportedAt' => $bug->getReportedAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $bug->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
                'kanbanCard' => $bug->getKanbanCard() ? [
                    'id' => $bug->getKanbanCard()->getId(),
                    'title' => $bug->getKanbanCard()->getTitle(),
                ] : null,
                'ourGameSlug' => $bug->getOurGame()?->getShortNameSlug(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'bugs' => $bugsData,
        ]);
    }

    #[Route('/kanban/api/activity/{entityType}/{entityId}', name: 'kanban_api_activity_log', requirements: ['entityId' => '\d+'], methods: ['GET'])]
    public function getActivityLogApi(string $entityType, int $entityId): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => 'Activity log endpoint is working (dummy data)',
            'activities' => [
                ['id' => 1, 'description' => 'Dummy activity 1 for '.$entityType.' '.$entityId, 'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM)],
                ['id' => 2, 'description' => 'Dummy activity 2 for '.$entityType.' '.$entityId, 'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM)],
            ],
        ]);
    }

    /**
     * Helper to get form errors in a structured way.
     */
    private function getFormErrors(FormInterface $form): array
    {
        $errors = ['_global' => [], 'fields' => []];
        foreach ($form->getErrors(true, true) as $error) {
            /** @var FormError $error */
            $propertyPath = $error->getCause() ? $error->getCause()->getPropertyPath() : null;
            $message = $error->getMessage();

            if ($propertyPath) {
                $errors['fields'][$propertyPath][] = $message;
            } else {
                $errors['_global'][] = $message;
            }
        }

        return $errors;
    }

    /**
     * Helper to handle database exceptions and return a consistent JsonResponse.
     */
    private function handleDatabaseException(\Exception $e): JsonResponse
    {
        $message = 'An unexpected database error occurred.';
        if ($e instanceof ORMException) {
            $message = 'Database ORM error: '.$e->getMessage();
        } elseif ($e instanceof DBALException) {
            $message = 'Database connection/query error: '.$e->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => $message], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Retrieves the authenticated user.
     *
     * @return User|null the authenticated user, or null if not authenticated
     */
    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

    /**
     * Checks if a user is authenticated. If not, returns an unauthorized JsonResponse.
     *
     * @return User|JsonResponse the authenticated user, or a JsonResponse if not authenticated
     */
    private function checkAuthentication(): User|JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (null === $user) {
            return new JsonResponse(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }
}
