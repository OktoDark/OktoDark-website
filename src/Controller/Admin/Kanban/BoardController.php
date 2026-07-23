<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin\Kanban;

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\User;
use App\Form\BoardFormType;
use App\Repository\BoardRepository;
use App\Repository\ModsRepository;
use App\Repository\OurGamesRepository;
use App\Repository\UserRepository;
use App\Security\Attribute\Permission;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/kanban/board', name: 'admin_kanban_')]
#[Permission('admin.board.index', group: 'Boards', label: 'Manage boards')]
class BoardController extends AbstractController
{
    /**
     * Initialize controller dependencies for boards, users, games and persistence.
     */
    public function __construct(
        private readonly BoardRepository $boardRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly OurGamesRepository $ourGamesRepository,
        private readonly ModsRepository $modsRepository,
    ) {
    }

    /**
     * Render the Kanban board list with a board creation form and available games.
     */
    #[Route('/', name: 'index')]
    #[Permission('admin.board.index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status', 'all');

        $filters = [];
        if ($search) {
            $filters['search'] = $search;
        }
        if ('public' === $status) {
            $filters['isPublic'] = true;
        } elseif ('private' === $status) {
            $filters['isPublic'] = false;
        }

        $boards = $this->boardRepository->findFiltered($filters);
        $createForm = $this->createForm(BoardFormType::class, new Board());
        $ourGames = $this->ourGamesRepository->findAll();
        $mods = $this->modsRepository->findAll();

        return $this->render('@theme/admin/kanban/boards/index.html.twig', [
            'boards' => $boards,
            'createForm' => $createForm->createView(),
            'ourGames' => $ourGames,
            'mods' => $mods,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Create a new board owned by the current user with a default "To Do" column.
     *
     * Validates the board and its generated default column before persisting;
     * surfaces ORM/DBAL errors as flash messages and redirects to the board list.
     */
    #[Route('/create', name: 'create')]
    #[Permission('admin.board.create')]
    public function create(Request $request): Response
    {
        $board = new Board();
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'User not authenticated.');

            return $this->redirectToRoute('admin_kanban_index');
        }
        $board->setOwner($user); // Set the owner BEFORE form handling

        $form = $this->createForm(BoardFormType::class, $board);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Create a default column for the new board
                $defaultColumn = new BoardColumn();
                $defaultColumn->setTitle('To Do');
                $defaultColumn->setPosition(0); // First column
                $defaultColumn->setBoard($board); // Explicitly set the board on the column
                $defaultColumn->setCreatedAt(new \DateTimeImmutable()); // Ensure dates are set
                $defaultColumn->setUpdatedAt(new \DateTimeImmutable()); // Ensure dates are set

                // Validate the default column after it has been associated with the board
                $columnErrors = $this->validator->validate($defaultColumn);
                if (\count($columnErrors) > 0) {
                    $errorMessages = [];
                    foreach ($columnErrors as $error) {
                        $errorMessages[] = $error->getMessage().' (Property: '.$error->getPropertyPath().')';
                    }
                    $this->addFlash('error', 'Failed to create board: Default column validation failed: '.implode(', ', $errorMessages));

                    return $this->redirectToRoute('admin_kanban_index');
                }

                $this->entityManager->persist($board); // Persist the board
                $this->entityManager->persist($defaultColumn); // Explicitly persist the column
                $this->entityManager->flush();

                // Check if the column was persisted by checking its ID
                if (null === $defaultColumn->getId()) {
                    $this->addFlash('error', 'Board created successfully, but the default "To Do" column failed to persist. This might indicate a database schema issue or a deeper persistence problem.');
                } else {
                    $this->addFlash('success', 'Board created successfully with default "To Do" column (ID: '.$defaultColumn->getId().')!');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', $this->getDatabaseErrorMessage($e, 'Failed to create board.'));
            }
        } elseif ($form->isSubmitted()) {
            $errors = $this->getFormErrors($form);
            $errorMessage = 'Failed to create board: ';
            $hasErrors = false;

            if (!empty($errors['_global'])) {
                $errorMessage .= implode(', ', $errors['_global']);
                $hasErrors = true;
            }

            if (!empty($errors['fields'])) {
                foreach ($errors['fields'] as $field => $fieldErrors) {
                    if ($hasErrors) {
                        $errorMessage .= '; ';
                    }
                    $errorMessage .= ucfirst($field).': '.implode(', ', $fieldErrors);
                    $hasErrors = true;
                }
            }

            if (!$hasErrors) {
                $errorMessage .= 'Unknown validation error. Please check form data.';
            }

            $this->addFlash('error', $errorMessage);
        }

        return $this->redirectToRoute('admin_kanban_index');
    }

    /**
     * Update an existing board from the submitted form and redirect to the list.
     *
     * Persists changes via flush, converting ORM/DBAL exceptions into flash
     * messages; on validation failure it reports structured form errors.
     */
    #[Route('/edit/{id}', name: 'edit', requirements: ['id' => '\d+'])]
    #[Permission('admin.board.edit')]
    public function edit(Request $request, Board $board): Response
    {
        $form = $this->createForm(BoardFormType::class, $board);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();
                $this->addFlash('success', 'Board updated successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', $this->getDatabaseErrorMessage($e, 'Failed to update board.'));
            }
        } elseif ($form->isSubmitted()) {
            // Enhanced error reporting for the 'edit' method
            $errors = $this->getFormErrors($form);
            $errorMessage = 'Failed to update board: ';
            $hasErrors = false;

            if (!empty($errors['_global'])) {
                $errorMessage .= 'Global errors: '.implode(', ', $errors['_global']);
                $hasErrors = true;
            }

            if (!empty($errors['fields'])) {
                foreach ($errors['fields'] as $field => $fieldErrors) {
                    if ($hasErrors) {
                        $errorMessage .= '; ';
                    }
                    $errorMessage .= ucfirst($field).': '.implode(', ', $fieldErrors);
                    $hasErrors = true;
                }
            }

            // Fallback for when isValid is false but no specific errors are found
            if (!$hasErrors) {
                $errorMessage .= 'Unknown validation error. This might be due to a data transformer or a non-field-specific constraint. Please check server logs for more details.';
            }

            $this->addFlash('error', $errorMessage);
        }

        return $this->redirectToRoute('admin_kanban_index');
    }

    /**
     * Delete a board after CSRF token validation and redirect to the list.
     */
    #[Route('/delete/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('admin.board.delete')]
    public function delete(Request $request, Board $board): Response
    {
        if ($this->isCsrfTokenValid('delete'.$board->getId(), $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($board);
                $this->entityManager->flush();
                $this->addFlash('success', 'Board deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', $this->getDatabaseErrorMessage($e, 'Failed to delete board.'));
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_kanban_index');
    }

    /**
     * Return users with high-rank roles who are not yet members or the owner of the board.
     *
     * Excludes existing members/owner and matches users whose roles contain any
     * of the configured high-rank roles (ROLE_ADMIN, ROLE_DEVELOPER).
     */
    #[Route('/api/boards/{id}/members/high-rank-available', name: 'api_board_members_high_rank_available', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Permission('admin.board.members.high_rank')]
    public function getHighRankAvailableMembersApi(int $id): JsonResponse
    {
        try {
            $board = $this->boardRepository->find($id);
            if (!$board) {
                return new JsonResponse(['success' => false, 'message' => 'Board not found'], Response::HTTP_NOT_FOUND);
            }

            $highRankRoles = ['ROLE_ADMIN', 'ROLE_DEVELOPER']; // Define high-rank roles

            $existingMemberIds = array_map(static fn (User $m) => $m->getId(), $board->getMembers()->toArray());
            if ($board->getOwner()) {
                $existingMemberIds[] = $board->getOwner()->getId();
            }
            $existingMemberIds = array_unique($existingMemberIds);

            $qb = $this->userRepository->createQueryBuilder('u')
                ->innerJoin('u.roleEntities', 'r')
                ->where('u.id NOT IN (:existingMemberIds)')
                ->setParameter('existingMemberIds', $existingMemberIds ?: [0]);

            $qb->andWhere('r.name IN (:highRankRoles)')
                ->setParameter('highRankRoles', $highRankRoles)
                ->groupBy('u.id');

            $availableUsers = $qb->orderBy('u.username', 'ASC')
                ->getQuery()
                ->getResult();

            return new JsonResponse([
                'success' => true,
                'members' => array_map(static fn (User $u) => [
                    'id' => $u->getId(),
                    'username' => $u->getUsername(),
                ], $availableUsers),
            ]);
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e, 'Error fetching available high-rank members: ');
        }
    }

    /**
     * Return the owner followed by the other members of a board as JSON.
     */
    #[Route('/api/boards/{id}/members/current', name: 'api_board_members_current', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Permission('admin.board.members.view')]
    public function getCurrentBoardMembersApi(int $id): JsonResponse
    {
        try {
            $board = $this->boardRepository->find($id);
            if (!$board) {
                return new JsonResponse(['success' => false, 'message' => 'Board not found'], Response::HTTP_NOT_FOUND);
            }

            $currentMembersData = [];

            // Add owner first
            if ($board->getOwner()) {
                $currentMembersData[] = [
                    'id' => $board->getOwner()->getId(),
                    'username' => $board->getOwner()->getUsername(),
                    'isOwner' => true,
                ];
            }

            // Add other members
            foreach ($board->getMembers() as $member) {
                if ($board->getOwner() && $member->getId() !== $board->getOwner()->getId()) {
                    $currentMembersData[] = [
                        'id' => $member->getId(),
                        'username' => $member->getUsername(),
                        'isOwner' => false,
                    ];
                }
            }

            return new JsonResponse([
                'success' => true,
                'members' => $currentMembersData,
            ]);
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e, 'Error fetching current board members: ');
        }
    }

    /**
     * Add a user as a member of a board, rejecting duplicates and invalid users.
     */
    #[Route('/api/boards/{id}/members', name: 'api_board_members_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('admin.board.members.add')]
    public function addBoardMemberApi(Request $request, int $id): JsonResponse
    {
        $board = $this->boardRepository->find($id);
        if (!$board) {
            return new JsonResponse(['success' => false, 'message' => 'Board not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;

        if (!$userId) {
            return new JsonResponse(['success' => false, 'message' => 'User ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $userToAdd = $this->userRepository->find($userId);

        if (!$userToAdd) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if ($board->isMember($userToAdd) || ($board->getOwner() && $board->getOwner()->getId() === $userToAdd->getId())) {
            return new JsonResponse(['success' => false, 'message' => 'User is already a member or owner of this board'], Response::HTTP_CONFLICT);
        }

        try {
            $board->addMember($userToAdd);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e, 'Failed to add member: ');
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Member added successfully!',
            'member' => [
                'id' => $userToAdd->getId(),
                'username' => $userToAdd->getUsername(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Remove a member from a board, refusing to remove the owner.
     */
    #[Route('/api/boards/{boardId}/members/{userId}', name: 'api_board_members_remove', requirements: ['boardId' => '\d+', 'userId' => '\d+'], methods: ['DELETE'])]
    #[Permission('admin.board.members.remove')]
    public function removeBoardMemberApi(int $boardId, int $userId): JsonResponse
    {
        $board = $this->boardRepository->find($boardId);
        if (!$board) {
            return new JsonResponse(['success' => false, 'message' => 'Board not found'], Response::HTTP_NOT_FOUND);
        }

        $userToRemove = $this->userRepository->find($userId);
        if (!$userToRemove) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$board->isMember($userToRemove)) {
            return new JsonResponse(['success' => false, 'message' => 'User is not a member of this board'], Response::HTTP_NOT_FOUND);
        }

        // Prevent removing the owner
        if ($board->getOwner() && $board->getOwner()->getId() === $userToRemove->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot remove board owner'], Response::HTTP_FORBIDDEN);
        }

        try {
            $board->removeMember($userToRemove);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e, 'Failed to remove member: ');
        }

        return new JsonResponse(['success' => true, 'message' => 'Member removed successfully!']);
    }

    /**
     * Helper to get form errors in a structured way.
     */
    private function getFormErrors(FormInterface $form): array
    {
        $errors = ['_global' => [], 'fields' => []];
        // Get all errors, including those not mapped to a specific field
        foreach ($form->getErrors(true) as $error) { // Removed the second 'true' to get all errors
            /** @var FormError $error */
            $cause = $error->getCause();
            $propertyPath = $cause && method_exists($cause, 'getPropertyPath') ? $cause->getPropertyPath() : null;
            $message = $error->getMessage();

            // If there's a property path, it's a field-specific error
            if ($propertyPath) {
                $errors['fields'][$propertyPath][] = $message;
            } else {
                // Otherwise, it's a global error or an error not directly tied to a field
                $errors['_global'][] = $message;
            }
        }

        return $errors;
    }

    /**
     * Helper to handle database exceptions and return a consistent JsonResponse.
     */
    private function handleDatabaseException(\Exception $e, string $prefixMessage = 'An unexpected database error occurred.'): JsonResponse
    {
        $message = $prefixMessage;
        if ($e instanceof ORMException) {
            $message .= ' Database ORM error: '.$e->getMessage();
        } elseif ($e instanceof DBALException) {
            $message .= ' Database connection/query error: '.$e->getMessage();
        } else {
            $message .= ' '.$e->getMessage();
        }

        return new JsonResponse(['success' => false, 'message' => $message], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Helper to get a user-friendly database error message for flash messages.
     */
    private function getDatabaseErrorMessage(\Exception $e, string $defaultMessage = 'A database error occurred.'): string
    {
        if ($e instanceof ORMException) {
            return $defaultMessage.' (ORM Error: '.$e->getMessage().')';
        } elseif ($e instanceof DBALException) {
            return $defaultMessage.' (DBAL Error: '.$e->getMessage().')';
        }

        return $defaultMessage.' (Error: '.$e->getMessage().')';
    }
}
