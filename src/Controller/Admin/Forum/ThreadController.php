<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin\Forum;

use App\Entity\ForumThread;
use App\Entity\User;
use App\Repository\ForumCategoryRepository;
use App\Repository\ForumThreadRepository;
use App\Security\Attribute\Permission;
use App\Service\ForumModeratorActionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/forum/thread')]
final class ThreadController extends AbstractController
{
    /**
     * Initialize thread repository, category repository, entity manager, and moderator action logger.
     */
    public function __construct(
        private ForumThreadRepository $threadRepo,
        private ForumCategoryRepository $catRepo,
        private EntityManagerInterface $em,
        private ForumModeratorActionService $moderatorLogger,
    ) {
    }

    /**
     * List forum threads with optional filters for category, status, and author.
     */
    #[Route('/', name: 'admin_forum_threads')]
    #[Permission('admin.forum.threads.index')]
    public function index(Request $request): Response
    {
        $categoryId = $request->query->get('category');
        $status = $request->query->get('status');
        $author = $request->query->get('author');

        $qb = $this->threadRepo->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC');

        if ($categoryId) {
            $qb->andWhere('t.category = :cat')->setParameter('cat', $categoryId);
        }

        if ($status) {
            if ('locked' === $status) {
                $qb->andWhere('t.locked = true');
            } elseif ('pinned' === $status) {
                $qb->andWhere('t.pinned = true');
            } elseif ('deleted' === $status) {
                $qb->andWhere('t.deletedAt IS NOT NULL');
            }
        }

        if ($author) {
            $qb->join('t.author', 'u')
                ->andWhere('u.username LIKE :author')
                ->setParameter('author', '%'.$author.'%');
        }

        return $this->render('@theme/admin/forum/threads.html.twig', [
            'threads' => $qb->getQuery()->getResult(),
            'categories' => $this->catRepo->findAll(),
            'currentFilters' => [
                'category' => $categoryId,
                'status' => $status,
                'author' => $author,
            ],
        ]);
    }

    /**
     * Toggle a thread's locked status and log the moderator action.
     */
    #[Route('/toggle-lock/{id}', name: 'admin_forum_thread_lock')]
    #[Permission('admin.forum.threads.lock')]
    public function toggleLock(ForumThread $thread): Response
    {
        $thread->setLocked(!$thread->isLocked());
        $this->em->flush();

        /** @var User $user */
        $user = $this->getUser();
        $this->moderatorLogger->log($user, $thread->isLocked() ? 'lock_thread' : 'unlock_thread', 'thread', $thread->getId(), $thread->getTitle());
        $this->addFlash('success', 'Thread lock status updated.');

        return $this->redirectToRoute('admin_forum_threads');
    }

    /**
     * Toggle a thread's pinned status and log the moderator action.
     */
    #[Route('/toggle-pin/{id}', name: 'admin_forum_thread_pin')]
    #[Permission('admin.forum.threads.pin')]
    public function togglePin(ForumThread $thread): Response
    {
        $thread->setPinned(!$thread->isPinned());
        $this->em->flush();

        /** @var User $user */
        $user = $this->getUser();
        $this->moderatorLogger->log($user, $thread->isPinned() ? 'pin_thread' : 'unpin_thread', 'thread', $thread->getId(), $thread->getTitle());
        $this->addFlash('success', 'Thread pin status updated.');

        return $this->redirectToRoute('admin_forum_threads');
    }

    /**
     * Restore a soft-deleted thread and log the moderator action.
     */
    #[Route('/restore/{id}', name: 'admin_forum_thread_restore')]
    #[Permission('admin.forum.threads.restore')]
    public function restore(ForumThread $thread): Response
    {
        $thread->setDeletedAt(null);
        $this->em->flush();

        /** @var User $user */
        $user = $this->getUser();
        $this->moderatorLogger->log($user, 'restore_thread', 'thread', $thread->getId(), $thread->getTitle());
        $this->addFlash('success', 'Thread restored.');

        return $this->redirectToRoute('admin_forum_threads');
    }

    /**
     * Permanently delete a thread after CSRF validation and log the moderator action.
     */
    #[Route('/delete/{id}', name: 'admin_forum_thread_delete', methods: ['POST'])]
    #[Permission('admin.forum.threads.delete')]
    public function delete(ForumThread $thread, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$thread->getId(), $request->request->get('_token'))) {
            $id = $thread->getId();
            $title = $thread->getTitle();
            $this->em->remove($thread);
            $this->em->flush();

            /** @var User $user */
            $user = $this->getUser();
            $this->moderatorLogger->log($user, 'permanent_delete_thread', 'thread', $id, $title);
            $this->addFlash('success', 'Thread permanently deleted.');
        }

        return $this->redirectToRoute('admin_forum_threads');
    }
}
