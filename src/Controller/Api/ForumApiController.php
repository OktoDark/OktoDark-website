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

use App\Entity\ForumCategory;
use App\Entity\ForumThread;
use App\Repository\ForumCategoryRepository;
use App\Repository\ForumThreadRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/forum')]
final class ForumApiController extends AbstractController
{
    /**
     * Retrieve a list of top-level forum categories.
     */
    #[Route('/categories', name: 'api_forum_categories', methods: ['GET'])]
    public function categories(ForumCategoryRepository $repo): JsonResponse
    {
        try {
            $categories = $repo->findBy(['parent' => null], ['position' => 'ASC']);
            $data = [];

            foreach ($categories as $cat) {
                $data[] = [
                    'id' => $cat->getId(),
                    'name' => $cat->getName(),
                    'slug' => $cat->getSlug(),
                    'description' => $cat->getDescription(),
                    'thread_count' => \count($cat->getThreads()),
                ];
            }

            return $this->json($data);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve forum categories');
        }
    }

    /**
     * Retrieve threads for a specific forum category.
     */
    #[Route('/category/{slug}/threads', name: 'api_forum_category_threads', methods: ['GET'])]
    public function threads(ForumCategory $category, ForumThreadRepository $threadRepo): JsonResponse
    {
        try {
            $threads = $threadRepo->findBy(['category' => $category, 'deletedAt' => null], ['createdAt' => 'DESC']);
            $data = [];

            foreach ($threads as $t) {
                $data[] = [
                    'id' => $t->getId(),
                    'title' => $t->getTitle(),
                    'slug' => $t->getSlug(),
                    'author' => $t->getAuthor()->getUsername(),
                    'views' => $t->getViews(),
                    'replies' => \count($t->getPosts()),
                    'created_at' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ];
            }

            return $this->json($data);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve forum threads for category');
        }
    }

    /**
     * Retrieve detailed information about a forum thread including its non-deleted posts.
     */
    #[Route('/thread/{slug}', name: 'api_forum_thread_detail', methods: ['GET'])]
    public function threadDetail(ForumThread $thread): JsonResponse
    {
        if ($thread->isDeleted()) {
            return $this->handleNotFound('Thread');
        }

        try {
            $posts = [];
            foreach ($thread->getPosts() as $p) {
                if ($p->isDeleted()) {
                    continue;
                }
                $posts[] = [
                    'id' => $p->getId(),
                    'content' => $p->getContent(),
                    'author' => $p->getAuthor()->getUsername(),
                    'created_at' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ];
            }

            return $this->json([
                'id' => $thread->getId(),
                'title' => $thread->getTitle(),
                'content' => $thread->getContent(),
                'author' => $thread->getAuthor()->getUsername(),
                'created_at' => $thread->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'posts' => $posts,
            ]);
        } catch (\Throwable $e) {
            return $this->handleServiceException($e, 'Failed to retrieve forum thread details');
        }
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
