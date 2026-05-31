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

use App\Entity\ForumCategory;
use App\Entity\ForumPost;
use App\Entity\ForumTag;
use App\Entity\ForumThread;
use App\Repository\ForumModerationLogRepository;
use App\Repository\ForumPostRepository;
use App\Repository\ForumThreadRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/forum')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private ForumThreadRepository $threadRepo,
        private ForumPostRepository $postRepo,
        private UserRepository $userRepo,
        private ForumModerationLogRepository $logRepo,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/dashboard', name: 'admin_forum_dashboard')]
    #[Route('', name: 'admin_forum_index')]
    public function index(): Response
    {
        return $this->render('@theme/admin/forum/dashboard.html.twig', [
            'stats' => [
                'total_threads' => $this->threadRepo->count([]),
                'total_posts' => $this->postRepo->count([]),
                'total_users' => $this->userRepo->count([]),
                'reported_posts' => $this->postRepo->count(['reported' => true]),
            ],
            'latest_threads' => $this->threadRepo->findBy([], ['createdAt' => 'DESC'], 5),
            'reported_items' => $this->postRepo->findBy(['reported' => true], ['createdAt' => 'DESC'], 5),
            'recent_logs' => $this->logRepo->findBy([], ['createdAt' => 'DESC'], 5),
        ]);
    }

    #[Route('/clean', name: 'admin_forum_clean', methods: ['POST'])]
    public function cleanForum(Request $request): Response
    {
        $option = $request->request->get('clean_option');

        if ('full-clear' === $option) {
            $this->addFlash('info', 'Clearing all forum data and resetting auto-increment counters...');

            $connection = $this->entityManager->getConnection();

            $postTableName = $this->entityManager->getClassMetadata(ForumPost::class)->getTableName();
            $threadTableName = $this->entityManager->getClassMetadata(ForumThread::class)->getTableName();
            $categoryTableName = $this->entityManager->getClassMetadata(ForumCategory::class)->getTableName();
            $tagTableName = $this->entityManager->getClassMetadata(ForumTag::class)->getTableName();

            try {
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');

                $connection->executeStatement('TRUNCATE TABLE '.$postTableName);
                $connection->executeStatement('TRUNCATE TABLE '.$threadTableName);
                $connection->executeStatement('TRUNCATE TABLE '.$categoryTableName);
                $connection->executeStatement('TRUNCATE TABLE '.$tagTableName);

                $this->addFlash('success', 'Forum has been completely wiped and auto-increment counters reset.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error clearing forum data: '.$e->getMessage());
            } finally {
                // Re-enable foreign key checks
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');
            }
        } elseif ('purge-deleted' === $option) {
            $dateLimit = new \DateTime('-30 days');

            try {
                $purgedPosts = $this->entityManager->createQuery(
                    'DELETE FROM App\Entity\ForumPost p WHERE p.deletedAt < :limit'
                )->setParameter('limit', $dateLimit)->execute();

                $purgedThreads = $this->entityManager->createQuery(
                    'DELETE FROM App\Entity\ForumThread t WHERE t.deletedAt < :limit'
                )->setParameter('limit', $dateLimit)->execute();

                $this->addFlash('success', \sprintf('Purged %d posts and %d threads older than 30 days.', $purgedPosts, $purgedThreads));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error purging deleted items: '.$e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid clean option provided.');
        }

        return $this->redirectToRoute('admin_forum_dashboard');
    }
}
