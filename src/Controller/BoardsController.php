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

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\Bug;
use App\Entity\Card;
use App\Entity\OurGames;
use App\Entity\User;
use App\Form\BoardFormType;
use App\Repository\BoardColumnRepository;
use App\Repository\BoardRepository;
use App\Repository\OurGamesRepository;
use App\Security\Attribute\Permission;
use App\Service\KanbanBugSyncService;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BoardsController extends AbstractController
{
    public function __construct(
        private readonly BoardRepository $boardRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly BoardColumnRepository $boardColumnRepository,
        private readonly OurGamesRepository $ourGamesRepository,
        private readonly KanbanBugSyncService $syncService,
    ) {
    }

    /**
     * Lists Kanban boards not linked to any specific game for the current user.
     *
     * Boards are filtered by the authenticated member and an explicit null
     * "ourGame" filter, so only general (non-project) boards are returned.
     */
    #[Route('/workspace/boards', name: 'kanban_index', methods: ['GET'])]
    #[Permission('kanban.view', group: 'Kanban', label: 'View Kanban')]
    public function index(
        #[MapEntity(mapping: ['shortNameSlug' => 'shortNameSlug'])] ?OurGames $game = null,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $filters = ['member' => $user];
        // For the general /workspace/boards route, we only want boards not linked to any game
        // The game-specific route is now handled by viewBoard
        $filters['ourGame'] = null;

        $boards = $this->boardRepository->findFiltered($filters);

        return $this->render('@theme/kanban/board-list.html.twig', [
            'createBoardForm' => $this->createForm(BoardFormType::class)->createView(),
            'boards' => $boards,
            'game' => null,
        ]);
    }

    /**
     * Displays a single Kanban board dashboard with all related data.
     *
     * Resolves the board for the given context. When only a game slug is
     * provided the board defaults to the game's bugLink board (or its first
     * associated board). The board/game context is validated and access is
     * granted via the "board_view" voter, then the board is reloaded with full
     * column, card and bug details to avoid lazy-loading issues.
     */
    #[Route('/workspace/boards/{id}', name: 'kanban_board', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/workspace/{shortNameSlug}/boards/{id?}', name: 'kanban_board_project', requirements: ['shortNameSlug' => '[a-zA-Z0-9_-]+', 'id' => '\d+'], methods: ['GET'])]
    #[Permission('kanban.view.board')]
    public function viewBoard(
        #[MapEntity(mapping: ['shortNameSlug' => 'shortNameSlug'])] ?OurGames $game = null,
        ?Board $board = null,
    ): Response {
        // If a game slug is provided but no board ID, try to find a default board for the game
        if ($game && !$board) {
            // Option 1: Use the bugLink board as the default board for the project
            $board = $game->getBugLink();
            if (!$board) {
                // Option 2: If no bugLink, get the first board associated with the game
                $boardsForGame = $this->boardRepository->findByOurGame($game);
                if (!empty($boardsForGame)) {
                    $board = $boardsForGame[0];
                }
            }

            if (!$board) {
                throw $this->createNotFoundException('No default board found for this project.');
            }
        } elseif (!$board) {
            // This case should only happen if a general board was requested without an ID, which is an error
            throw $this->createNotFoundException('Board ID is missing.');
        }

        // Ensure the board belongs to the correct game context if a game is provided
        if ($game && $board->getOurGame() !== $game) {
            throw $this->createNotFoundException('Board not found for this project.');
        } elseif (!$game && null !== $board->getOurGame()) {
            // If no game context is provided in the URL, but the board is linked to a game, it's a mismatch
            throw $this->createNotFoundException('Board not found for this context.');
        }

        $this->denyAccessUnlessGranted('board_view', $board);

        // Load board with all related data (columns, cards, bugs, etc) to prevent lazy-loading issues
        $boardId = $board->getId();
        $board = $this->boardRepository->findBoardWithDetails($boardId);

        if (!$board) {
            throw $this->createNotFoundException('Board not found');
        }

        return $this->render('@theme/kanban/board-dashboard.html.twig', [
            'board' => $board,
            'boardForm' => $this->createForm(BoardFormType::class, $board)->createView(),
            'game' => $game,
        ]);
    }

    /**
     * API: Reports a bug as a new card on the given board.
     *
     * Creates a high-priority bug card placed in the board's first column and
     * an associated Bug entity. Validates both entities and links the bug to the
     * game supplied in the payload (falling back to the board's game). Persistence
     * errors are normalised through handleDatabaseException().
     */
    #[Route('/kanban/api/boards/{id}/report-bug', name: 'kanban_api_board_report_bug', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('kanban.bug.report')]
    public function reportBugApi(Request $request, Board $board): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        /** @var User $user */
        $user = $authResult;

        $this->denyAccessUnlessGranted('board_view', $board);

        $data = json_decode($request->getContent(), true);
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;
        $severity = $data['severity'] ?? Bug::SEVERITY_MEDIUM;
        $reproductionSteps = $data['reproductionSteps'] ?? null;
        $expectedResult = $data['expectedResult'] ?? null;
        $actualResult = $data['actualResult'] ?? null;
        $operatingSystem = $data['operatingSystem'] ?? null;
        $operatingSystemVersion = $data['operatingSystemVersion'] ?? null;

        if (!$title || !$description) {
            return new JsonResponse(['success' => false, 'message' => 'Title and description are required'], Response::HTTP_BAD_REQUEST);
        }

        $columns = $board->getColumns();
        if ($columns->isEmpty()) {
            return new JsonResponse(['success' => false, 'message' => 'Board has no columns to place the bug card'], Response::HTTP_BAD_REQUEST);
        }
        $firstColumn = $columns->first();

        $newCardPosition = $firstColumn->getCards()->count();

        $card = new Card();
        $card->setTitle($title);
        $card->setDescription($description);
        $card->setType(Card::TYPE_BUG);
        $card->setPriority(Card::PRIORITY_HIGH);
        $card->setBoard($board);
        $card->setCreatedBy($user);
        $card->setColumn($firstColumn);
        $card->setPosition($newCardPosition);
        $card->setCreatedAt(new \DateTimeImmutable());
        $card->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($card);
        if (\count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['success' => false, 'message' => 'Card validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $bug = new Bug();
        $bug->setTitle($title);
        $bug->setDescription($description);
        $bug->setSeverity($severity);
        $bug->setReporter($user);
        $bug->setCard($card);
        $bug->setReproductionSteps($reproductionSteps);
        $bug->setExpectedResult($expectedResult);
        $bug->setActualResult($actualResult);
        $bug->setOperatingSystem($operatingSystem);
        $bug->setOperatingSystemVersion($operatingSystemVersion);

        $ourGame = null;
        $ourGameId = $data['ourGame'] ?? null; // Get ourGame ID from the request payload
        if ($ourGameId) {
            $ourGame = $this->ourGamesRepository->find($ourGameId);
            if (!$ourGame) {
                return new JsonResponse(['success' => false, 'message' => 'Selected game not found'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            // Fallback to the board's associated game if not provided in the payload
            // Need to load board fresh to ensure ourGame relationship is available
            $freshBoard = $this->boardRepository->find($board->getId());
            if ($freshBoard) {
                $ourGame = $freshBoard->getOurGame();
            } else {
                $ourGame = $board->getOurGame();
            }
        }
        $bug->setOurGame($ourGame);

        $errors = $this->validator->validate($bug);
        if (\count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['success' => false, 'message' => 'Bug validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->persist($card);
            $this->entityManager->persist($bug);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Bug reported successfully!',
            'card' => [
                'id' => $card->getId(),
                'title' => $card->getTitle(),
                'description' => $card->getDescription(),
                'type' => $card->getType(),
                'priority' => $card->getPriority(),
                'reporter' => $card->getCreatedBy()?->getUsername(),
            ],
            'bug' => [
                'id' => $bug->getId(),
                'title' => $bug->getTitle(),
                'severity' => $bug->getSeverity(),
                'status' => $bug->getStatus(),
                'operatingSystem' => $bug->getOperatingSystem(),
                'operatingSystemVersion' => $bug->getOperatingSystemVersion(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * API: Adds a new column to the given board.
     *
     * Validates the column (title required) and appends it at the end of the
     * board's columns. Requires "board_edit" permission.
     */
    #[Route('/kanban/api/boards/{id}/columns', name: 'kanban_api_board_add_column', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('kanban.column.create')]
    public function addColumnApi(Request $request, Board $board): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        // No need to cast $user here, as it's not directly used after authentication check

        $this->denyAccessUnlessGranted('board_edit', $board);

        $data = json_decode($request->getContent(), true);
        $title = $data['title'] ?? null;

        if (!$title) {
            return new JsonResponse(['success' => false, 'message' => 'Column title is required'], Response::HTTP_BAD_REQUEST);
        }

        $column = new BoardColumn();
        $column->setTitle($title);
        $column->setBoard($board);
        $column->setPosition($board->getColumns()->count());

        $errors = $this->validator->validate($column);
        if (\count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->persist($column);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Column created successfully!',
            'column' => [
                'id' => $column->getId(),
                'title' => $column->getTitle(),
                'position' => $column->getPosition(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * API: Renames an existing column.
     *
     * Updates the column title (after validating the board association and
     * "board_edit" permission) and stamps the updated time.
     */
    #[Route('/kanban/api/columns/{id}', name: 'kanban_api_column_update_title', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[Permission('kanban.column.edit')]
    public function updateColumnTitleApi(Request $request, BoardColumn $column): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        // No need to cast $user here, as it's not directly used after authentication check

        $board = $column->getBoard();
        if (!$board) {
            return new JsonResponse(['success' => false, 'message' => 'Column is not associated with a board'], Response::HTTP_BAD_REQUEST);
        }
        $this->denyAccessUnlessGranted('board_edit', $board);

        $data = json_decode($request->getContent(), true);
        $newTitle = $data['title'] ?? null;

        if (!$newTitle) {
            return new JsonResponse(['success' => false, 'message' => 'New title is required'], Response::HTTP_BAD_REQUEST);
        }

        $column->setTitle($newTitle);
        $column->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($column);
        if (\count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Column title updated successfully!',
            'column' => [
                'id' => $column->getId(),
                'title' => $column->getTitle(),
            ],
        ]);
    }

    /**
     * API: Reorders the columns of a board.
     *
     * Accepts an ordered list of column ids and applies the new positions to
     * the board's columns. Columns not belonging to the board are rejected.
     * Requires "board_edit" permission.
     */
    #[Route('/kanban/api/boards/{id}/columns/reorder', name: 'kanban_api_board_columns_reorder', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[Permission('kanban.column.reorder')]
    public function reorderColumnsApi(Request $request, Board $board): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        // No need to cast $user here, as it's not directly used after authentication check

        $this->denyAccessUnlessGranted('board_edit', $board);

        $data = json_decode($request->getContent(), true);
        $columnIds = $data['columnIds'] ?? null;

        if (!\is_array($columnIds)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid column IDs provided'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $columns = $board->getColumns()->toArray();
            $columnMap = [];
            foreach ($columns as $column) {
                $columnMap[$column->getId()] = $column;
            }

            foreach ($columnIds as $index => $columnId) {
                if (isset($columnMap[$columnId])) {
                    $columnMap[$columnId]->setPosition($index);
                    $columnMap[$columnId]->setUpdatedAt(new \DateTimeImmutable());
                } else {
                    return new JsonResponse(['success' => false, 'message' => 'Column with ID '.$columnId.' not found in board'], Response::HTTP_BAD_REQUEST);
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Column order updated successfully!',
                'columns' => array_map(static fn ($col) => ['id' => $col->getId(), 'position' => $col->getPosition()], array_values($columnMap)),
            ]);
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e);
        }
    }

    /**
     * API: Moves a card to a different column and position.
     *
     * Validates the target column belongs to the same board (requires
     * "board_view"). Moving a card into a "done/completed/closed" column is
     * blocked while the card still has unresolved bugs via KanbanBugSyncService.
     */
    #[Route('/kanban/api/cards/{id}/move', name: 'kanban_api_cards_move', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[Permission('kanban.card.move')]
    public function moveCardApi(Request $request, Card $card): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        /** @var User $user */
        $user = $authResult;

        $board = $card->getBoard();
        if (!$board) {
            return new JsonResponse(['success' => false, 'message' => 'Card is not associated with a board'], Response::HTTP_BAD_REQUEST);
        }
        $this->denyAccessUnlessGranted('board_view', $board);

        $data = json_decode($request->getContent(), true);
        $newColumnId = $data['columnId'] ?? null;
        $newPosition = $data['position'] ?? null;

        if (null === $newColumnId || null === $newPosition) {
            return new JsonResponse(['success' => false, 'message' => 'New column ID and position are required'], Response::HTTP_BAD_REQUEST);
        }

        $newColumn = $this->boardColumnRepository->find($newColumnId);

        if (!$newColumn) {
            return new JsonResponse(['success' => false, 'message' => 'Target column not found'], Response::HTTP_NOT_FOUND);
        }

        if ($newColumn->getBoard()->getId() !== $board->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Target column does not belong to the same board'], Response::HTTP_BAD_REQUEST);
        }

        if ('done' === mb_strtolower($newColumn->getTitle()) || 'completed' === mb_strtolower($newColumn->getTitle()) || 'closed' === mb_strtolower($newColumn->getTitle())) {
            if ($this->syncService->hasUnresolvedBugs($card)) {
                $unresolvedCount = $this->syncService->getUnresolvedBugCount($card);
                $this->syncService->notifyCannotCloseCardWithBugs($card, $user);

                return new JsonResponse([
                    'success' => false,
                    'message' => "Cannot move card to '{$newColumn->getTitle()}' because it has {$unresolvedCount} unresolved bug(s). Please resolve all bugs first.",
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $card->setColumn($newColumn);
            $card->setPosition($newPosition);
            $card->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Card moved successfully!',
                'card' => [
                    'id' => $card->getId(),
                    'columnId' => $newColumn->getId(),
                    'position' => $card->getPosition(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to move card: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * API: Returns the boards visible to the current user.
     *
     * Supports optional filtering by game slug. Boards are resolved through
     * BoardRepository::findFiltered using the authenticated member as a filter.
     */
    #[Route('/kanban/api/boards', name: 'kanban_api_boards_list', methods: ['GET'])]
    #[Permission('kanban.view.api.board')]
    public function getBoardsApi(Request $request): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        /** @var User $user */
        $user = $authResult;

        $filters = ['member' => $user];
        $gameSlug = $request->query->get('gameSlug');
        $ourGame = null;

        if ($gameSlug) {
            $ourGame = $this->ourGamesRepository->findOneBy(['shortNameSlug' => $gameSlug]);
            if (!$ourGame) {
                return new JsonResponse(['success' => false, 'message' => 'Project not found'], Response::HTTP_NOT_FOUND);
            }
            $filters['ourGame'] = $ourGame;
        } else {
            $filters['ourGame'] = null; // Only show boards not linked to any game
        }

        $boards = $this->boardRepository->findFiltered($filters);

        $boardsData = [];
        foreach ($boards as $board) {
            $boardsData[] = [
                'id' => $board->getId(),
                'title' => $board->getTitle(),
                'description' => $board->getDescription(),
                'backgroundColor' => $board->getBackgroundColor(),
                'isPublic' => $board->isPublic(),
                'owner' => $board->getOwner()?->getUsername(),
                'membersCount' => $board->getMembers()->count(),
                'columnsCount' => $board->getColumns()->count(),
                'cardsCount' => $board->getCards()->count(),
                'ourGameSlug' => $board->getOurGame()?->getShortNameSlug(), // Added ourGameSlug
            ];
        }

        return new JsonResponse(['boards' => $boardsData]);
    }

    /**
     * API: Updates an existing board's properties.
     *
     * Submits the JSON payload to the board form (without clearing missing
     * fields), validates it, and persists changes. Requires "board_edit"
     * permission; the OurGames association is handled by the form.
     */
    #[Route('/kanban/api/boards/{id}', name: 'kanban_api_boards_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[Permission('kanban.board.edit')]
    public function updateBoardApi(Request $request, Board $board): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        // No need to cast $user here, as it's not directly used after authentication check

        $this->denyAccessUnlessGranted('board_edit', $board);

        $form = $this->createForm(BoardFormType::class, $board, ['method' => 'PUT']);

        $data = json_decode($request->getContent(), true);
        $form->submit($data, false); // Submit the entire data array to the form, but don't clear missing fields

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Removed redundant manual ourGame handling.
                // The form should handle setting the OurGames entity based on the submitted ID.
                $this->entityManager->flush();
            } catch (\Exception $e) {
                return $this->handleDatabaseException($e);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Board updated successfully!',
                'board' => [
                    'id' => $board->getId(),
                    'title' => $board->getTitle(),
                    'description' => $board->getDescription(),
                    'backgroundColor' => $board->getBackgroundColor(),
                    'isPublic' => $board->isPublic(),
                    'owner' => $board->getOwner()?->getUsername(),
                    'ourGameSlug' => $board->getOurGame()?->getShortNameSlug(),
                ],
            ], Response::HTTP_OK);
        }

        $errors = $this->getFormErrors($form);

        return new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * API: Creates a new board owned by the current user.
     *
     * Validates the submitted board form, creates the board with a default
     * "To Do" column, and persists both. Requires "kanban.board.create"
     * permission; the authenticated user becomes the board owner.
     */
    #[Route('/kanban/api/boards', name: 'kanban_api_boards_create', methods: ['POST'])]
    #[Permission('kanban.board.create')]
    public function createBoardApi(Request $request): JsonResponse
    {
        $board = new Board();
        $form = $this->createForm(BoardFormType::class, $board);

        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        /** @var User $user */
        $user = $authResult;
        $board->setOwner($user);

        $data = json_decode($request->getContent(), true);
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $defaultColumn = new BoardColumn();
                $defaultColumn->setTitle('To Do');
                $defaultColumn->setPosition(0);
                $defaultColumn->setBoard($board);

                $board->addColumn($defaultColumn);

                $columnErrors = $this->validator->validate($defaultColumn);
                if (\count($columnErrors) > 0) {
                    $errorMessages = [];
                    foreach ($columnErrors as $error) {
                        $errorMessages[] = $error->getMessage().' (Property: '.$error->getPropertyPath().')';
                    }

                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Default column validation failed',
                        'errors' => $errorMessages,
                    ], Response::HTTP_BAD_REQUEST);
                }

                $this->entityManager->persist($board);
                $this->entityManager->flush();

                if (null === $defaultColumn->getId()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Board created, but the default "To Do" column failed to persist. This might indicate a database schema issue or a deeper persistence problem.',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            } catch (\Exception $e) {
                return $this->handleDatabaseException($e);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Board created successfully!',
                'board' => [
                    'id' => $board->getId(),
                    'title' => $board->getTitle(),
                    'description' => $board->getDescription(),
                    'backgroundColor' => $board->getBackgroundColor(),
                    'isPublic' => $board->isPublic(),
                    'owner' => $board->getOwner()?->getUsername(),
                    'ourGameSlug' => $board->getOurGame()?->getShortNameSlug(),
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

    /**
     * API: Returns the owner and members of a board.
     *
     * Requires "board_view" permission. The owner is always listed first as
     * "isOwner", followed by the remaining board members.
     */
    #[Route('/kanban/api/boards/{id}/members/current', name: 'kanban_api_board_members_current', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Permission('kanban.view.api.board')]
    public function getBoardMembersApi(Board $board): JsonResponse
    {
        $authResult = $this->checkAuthentication();
        if ($authResult instanceof JsonResponse) {
            return $authResult;
        }
        // No need to cast $user here, as it's not directly used after authentication check

        $this->denyAccessUnlessGranted('board_view', $board);

        $membersData = [];
        if ($board->getOwner()) {
            $membersData[] = [
                'id' => $board->getOwner()->getId(),
                'username' => $board->getOwner()->getUsername(),
                'isOwner' => true,
            ];
        }

        foreach ($board->getMembers() as $member) {
            if ($member->getId() !== $board->getOwner()->getId()) {
                $membersData[] = [
                    'id' => $member->getId(),
                    'username' => $member->getUsername(),
                    'isOwner' => false,
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'members' => $membersData,
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
     * @return User|JsonResponse the authenticated user, or a JsonResponse if not authenticated
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
