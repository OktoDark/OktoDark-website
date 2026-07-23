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

use App\Entity\BugTracker;
use App\Entity\User;
use App\Form\BugTrackerFormType;
use App\Repository\BugTrackerRepository;
use App\Repository\ModsRepository;
use App\Repository\OurGamesRepository;
use App\Repository\UserRepository;
use App\Security\Attribute\Permission;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/kanban/bug-tracker', name: 'admin_bug_tracker_')]
#[Permission('admin.bug_tracker.index', group: 'Bug Trackers', label: 'Manage bug trackers')]
class BugTrackerController extends AbstractController
{
    public function __construct(
        private readonly BugTrackerRepository $bugTrackerRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly OurGamesRepository $ourGamesRepository,
        private readonly ModsRepository $modsRepository,
    ) {
    }

    #[Route('/', name: 'index')]
    #[Permission('admin.bug_tracker.view')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $activeFilter = $request->query->get('active');
        $activeOnly = null;
        if (null !== $activeFilter && '' !== $activeFilter) {
            $activeOnly = '1' === $activeFilter;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        [$bugTrackers, $total] = $this->bugTrackerRepository->findPaginated($search, $activeOnly, $page, $limit);

        $createForm = $this->createForm(BugTrackerFormType::class, new BugTracker());
        $ourGames = $this->ourGamesRepository->findAll();
        $mods = $this->modsRepository->findAll();

        return $this->render('@theme/admin/kanban/bug-tracker/index.html.twig', [
            'bugTrackers' => $bugTrackers,
            'createForm' => $createForm->createView(),
            'ourGames' => $ourGames,
            'mods' => $mods,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
            'search' => $search,
            'activeFilter' => $activeFilter,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    #[Permission('admin.bug_tracker.create')]
    public function create(Request $request): Response
    {
        $bugTracker = new BugTracker();
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'User not authenticated.');

            return $this->redirectToRoute('admin_bug_tracker_index');
        }

        $bugTracker->setOwner($user);

        $form = $this->createForm(BugTrackerFormType::class, $bugTracker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->persist($bugTracker);
                $this->entityManager->flush();

                $this->addFlash('success', 'Bug tracker created successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', $this->getDatabaseErrorMessage($e, 'Failed to create bug tracker.'));
            }
        } elseif ($form->isSubmitted()) {
            $errors = $this->getFormErrors($form);
            $this->addFlash('error', 'Failed to create bug tracker: '.$this->formatFormErrors($errors));
        }

        return $this->redirectToRoute('admin_bug_tracker_index');
    }

    #[Route('/edit/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('admin.bug_tracker.edit')]
    public function edit(Request $request, BugTracker $bugTracker): Response
    {
        $form = $this->createForm(BugTrackerFormType::class, $bugTracker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->entityManager->flush();
                $this->addFlash('success', 'Bug tracker updated successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', $this->getDatabaseErrorMessage($e, 'Failed to update bug tracker.'));
            }
        } elseif ($form->isSubmitted()) {
            $errors = $this->getFormErrors($form);
            $this->addFlash('error', 'Failed to update bug tracker: '.$this->formatFormErrors($errors));
        }

        return $this->redirectToRoute('admin_bug_tracker_index');
    }

    #[Route('/delete/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('admin.bug_tracker.delete')]
    public function delete(Request $request, BugTracker $bugTracker): Response
    {
        if ($this->isCsrfTokenValid('delete_bug_tracker'.$bugTracker->getId(), $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($bugTracker);
                $this->entityManager->flush();
                $this->addFlash('success', 'Bug tracker deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', $this->getDatabaseErrorMessage($e, 'Failed to delete bug tracker.'));
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_bug_tracker_index');
    }

    #[Route('/api/trackers', name: 'api_list', methods: ['GET'])]
    #[Permission('admin.bug_tracker.api_list')]
    public function apiList(Request $request): JsonResponse
    {
        $search = $request->query->get('search');
        $activeOnly = null;
        $activeFilter = $request->query->get('active');
        if (null !== $activeFilter && '' !== $activeFilter) {
            $activeOnly = '1' === $activeFilter;
        }

        $qb = $this->bugTrackerRepository->createListQueryBuilder($search, $activeOnly);

        if ($search) {
            $qb->setMaxResults(50);
        }

        $trackers = $qb->getQuery()->getResult();

        return new JsonResponse([
            'trackers' => array_map(static function (BugTracker $tracker) {
                return [
                    'id' => $tracker->getId(),
                    'name' => $tracker->getName(),
                    'slug' => $tracker->getSlug(),
                    'active' => $tracker->isActive(),
                    'project' => $tracker->getOurGame()?->getName(),
                    'owner' => $tracker->getOwner()?->getUsername(),
                ];
            }, $trackers),
        ]);
    }

    #[Route('/api/trackers/available', name: 'api_available', methods: ['GET'])]
    #[Permission('admin.bug_tracker.api_available')]
    public function apiAvailable(Request $request): JsonResponse
    {
        $users = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'users' => array_map(static function (User $u) {
                return [
                    'id' => $u->getId(),
                    'username' => $u->getUsername(),
                ];
            }, $users),
        ]);
    }

    #[Route('/api/trackers/{id}/trackers', name: 'api_trackers_current', methods: ['GET'])]
    #[Permission('admin.bug_tracker.api_current_trackers')]
    public function apiCurrentTrackers(int $id): JsonResponse
    {
        $bugTracker = $this->bugTrackerRepository->find($id);
        if (!$bugTracker) {
            return new JsonResponse(['success' => false, 'message' => 'Bug tracker not found'], Response::HTTP_NOT_FOUND);
        }

        $trackers = [];
        foreach ($bugTracker->getTrackers() as $tracker) {
            $trackers[] = [
                'id' => $tracker->getId(),
                'username' => $tracker->getUsername(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'trackers' => $trackers,
        ]);
    }

    #[Route('/api/trackers/{id}/trackers', name: 'api_trackers_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('admin.bug_tracker.api_add_tracker')]
    public function apiAddTracker(Request $request, int $id): JsonResponse
    {
        $bugTracker = $this->bugTrackerRepository->find($id);
        if (!$bugTracker) {
            return new JsonResponse(['success' => false, 'message' => 'Bug tracker not found'], Response::HTTP_NOT_FOUND);
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

        if ($bugTracker->isTracker($userToAdd)) {
            return new JsonResponse(['success' => false, 'message' => 'User is already assigned to this bug tracker'], Response::HTTP_CONFLICT);
        }

        if ($bugTracker->getOwner() && $bugTracker->getOwner()->getId() === $userToAdd->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Owner cannot be added as a tracker'], Response::HTTP_CONFLICT);
        }

        try {
            $bugTracker->addTracker($userToAdd);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e, 'Failed to add tracker: ');
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Tracker assigned successfully!',
            'tracker' => [
                'id' => $userToAdd->getId(),
                'username' => $userToAdd->getUsername(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/trackers/{trackerId}/trackers/{userId}', name: 'api_trackers_remove', requirements: ['trackerId' => '\d+', 'userId' => '\d+'], methods: ['DELETE'])]
    #[Permission('admin.bug_tracker.api_remove_tracker')]
    public function apiRemoveTracker(int $trackerId, int $userId): JsonResponse
    {
        $bugTracker = $this->bugTrackerRepository->find($trackerId);
        if (!$bugTracker) {
            return new JsonResponse(['success' => false, 'message' => 'Bug tracker not found'], Response::HTTP_NOT_FOUND);
        }

        $userToRemove = $this->userRepository->find($userId);
        if (!$userToRemove) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$bugTracker->isTracker($userToRemove)) {
            return new JsonResponse(['success' => false, 'message' => 'User is not assigned to this bug tracker'], Response::HTTP_NOT_FOUND);
        }

        if ($bugTracker->getOwner() && $bugTracker->getOwner()->getId() === $userToRemove->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot remove owner from tracker'], Response::HTTP_FORBIDDEN);
        }

        try {
            $bugTracker->removeTracker($userToRemove);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e, 'Failed to remove tracker: ');
        }

        return new JsonResponse(['success' => true, 'message' => 'Tracker removed successfully!']);
    }

    #[Route('/api/trackers/{id}/members/current', name: 'api_members_current', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Permission('admin.bug_tracker.members.view')]
    public function apiCurrentMembers(int $id): JsonResponse
    {
        $bugTracker = $this->bugTrackerRepository->find($id);
        if (!$bugTracker) {
            return new JsonResponse(['success' => false, 'message' => 'Bug tracker not found'], Response::HTTP_NOT_FOUND);
        }

        $currentMembersData = [];

        if ($bugTracker->getOwner()) {
            $currentMembersData[] = [
                'id' => $bugTracker->getOwner()->getId(),
                'username' => $bugTracker->getOwner()->getUsername(),
                'isOwner' => true,
            ];
        }

        foreach ($bugTracker->getTrackers() as $tracker) {
            $currentMembersData[] = [
                'id' => $tracker->getId(),
                'username' => $tracker->getUsername(),
                'isOwner' => false,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'members' => $currentMembersData,
        ]);
    }

    #[Route('/api/trackers/{id}/members/high-rank-available', name: 'api_members_high_rank_available', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Permission('admin.bug_tracker.members.high_rank')]
    public function apiMembersHighRankAvailable(int $id): JsonResponse
    {
        $bugTracker = $this->bugTrackerRepository->find($id);
        if (!$bugTracker) {
            return new JsonResponse(['success' => false, 'message' => 'Bug tracker not found'], Response::HTTP_NOT_FOUND);
        }

        $existingMemberIds = array_map(static fn (User $u) => $u->getId(), $bugTracker->getTrackers()->toArray());
        if ($bugTracker->getOwner()) {
            $existingMemberIds[] = $bugTracker->getOwner()->getId();
        }
        $existingMemberIds = array_unique($existingMemberIds);

        $highRankRoles = ['ROLE_ADMIN', 'ROLE_DEVELOPER'];

        $qb = $this->userRepository->createQueryBuilder('u')
            ->innerJoin('u.roleEntities', 'r')
            ->where('u.id NOT IN (:existingMemberIds)')
            ->setParameter('existingMemberIds', $existingMemberIds ?: [0]);

        $qb->andWhere('r.name IN (:highRankRoles)')
            ->setParameter('highRankRoles', $highRankRoles)
            ->groupBy('u.id');

        $availableUsers = $qb->orderBy('u.username', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'success' => true,
            'members' => array_map(static fn (User $u) => [
                'id' => $u->getId(),
                'username' => $u->getUsername(),
            ], $availableUsers),
        ]);
    }

    #[Route('/api/trackers/{id}/members', name: 'api_members_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('admin.bug_tracker.members.add')]
    public function apiAddMember(Request $request, int $id): JsonResponse
    {
        $bugTracker = $this->bugTrackerRepository->find($id);
        if (!$bugTracker) {
            return new JsonResponse(['success' => false, 'message' => 'Bug tracker not found'], Response::HTTP_NOT_FOUND);
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

        if ($bugTracker->isTracker($userToAdd)) {
            return new JsonResponse(['success' => false, 'message' => 'User is already assigned to this bug tracker'], Response::HTTP_CONFLICT);
        }

        if ($bugTracker->getOwner() && $bugTracker->getOwner()->getId() === $userToAdd->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Owner cannot be added as a tracker'], Response::HTTP_CONFLICT);
        }

        try {
            $bugTracker->addTracker($userToAdd);
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

    #[Route('/api/trackers/{trackerId}/members/{userId}', name: 'api_members_remove', requirements: ['trackerId' => '\d+', 'userId' => '\d+'], methods: ['DELETE'])]
    #[Permission('admin.bug_tracker.members.remove')]
    public function apiRemoveMember(int $trackerId, int $userId): JsonResponse
    {
        $bugTracker = $this->bugTrackerRepository->find($trackerId);
        if (!$bugTracker) {
            return new JsonResponse(['success' => false, 'message' => 'Bug tracker not found'], Response::HTTP_NOT_FOUND);
        }

        $userToRemove = $this->userRepository->find($userId);
        if (!$userToRemove) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$bugTracker->isTracker($userToRemove)) {
            return new JsonResponse(['success' => false, 'message' => 'User is not assigned to this bug tracker'], Response::HTTP_NOT_FOUND);
        }

        if ($bugTracker->getOwner() && $bugTracker->getOwner()->getId() === $userToRemove->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot remove owner from bug tracker'], Response::HTTP_FORBIDDEN);
        }

        try {
            $bugTracker->removeTracker($userToRemove);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleDatabaseException($e, 'Failed to remove member: ');
        }

        return new JsonResponse(['success' => true, 'message' => 'Member removed successfully!']);
    }

    #[Route('/toggle/{id}', name: 'toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Permission('admin.bug_tracker.toggle')]
    public function toggleActive(Request $request, BugTracker $bugTracker): Response
    {
        if ($this->isCsrfTokenValid('toggle_bug_tracker'.$bugTracker->getId(), $request->request->get('_token'))) {
            try {
                $bugTracker->setIsActive(!$bugTracker->isActive());
                $this->entityManager->flush();

                $status = $bugTracker->isActive() ? 'activated' : 'deactivated';
                $this->addFlash('success', 'Bug tracker '.$status.' successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', $this->getDatabaseErrorMessage($e, 'Failed to toggle bug tracker.'));
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('admin_bug_tracker_index');
    }

    private function getFormErrors(FormInterface $form): array
    {
        $errors = ['_global' => [], 'fields' => []];
        foreach ($form->getErrors(true, true) as $error) {
            $cause = $error->getCause();
            $propertyPath = $cause && method_exists($cause, 'getPropertyPath') ? $cause->getPropertyPath() : null;
            $message = $error->getMessage();

            if ($propertyPath) {
                $errors['fields'][$propertyPath][] = $message;
            } else {
                $errors['_global'][] = $message;
            }
        }

        return $errors;
    }

    private function formatFormErrors(array $errors): string
    {
        $parts = [];

        if (!empty($errors['_global'])) {
            $parts[] = implode(', ', $errors['_global']);
        }

        if (!empty($errors['fields'])) {
            foreach ($errors['fields'] as $field => $fieldErrors) {
                $parts[] = ucfirst($field).': '.implode(', ', $fieldErrors);
            }
        }

        return $parts ? implode(', ', $parts) : 'Unknown validation error.';
    }

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
