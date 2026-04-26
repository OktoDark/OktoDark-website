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
use App\Service\ForumModeratorActionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/forum/thread')]
#[IsGranted('ROLE_ADMIN')]
final class ThreadController extends AbstractController
{
    public function __construct(
        private ForumThreadRepository $threadRepo,
        private ForumCategoryRepository $catRepo,
        private EntityManagerInterface $em,
        private ForumModeratorActionService $moderatorLogger,
    ) {
    }

    #[Route('/', name: 'admin_forum_threads')]
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

    #[Route('/toggle-lock/{id}', name: 'admin_forum_thread_lock')]
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

    #[Route('/toggle-pin/{id}', name: 'admin_forum_thread_pin')]
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

    #[Route('/restore/{id}', name: 'admin_forum_thread_restore')]
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

    #[Route('/delete/{id}', name: 'admin_forum_thread_delete', methods: ['POST'])]
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
