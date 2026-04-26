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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/forum')]
final class ForumApiController extends AbstractController
{
    #[Route('/categories', name: 'api_forum_categories', methods: ['GET'])]
    public function categories(ForumCategoryRepository $repo): JsonResponse
    {
        $categories = $repo->findBy(['parent' => null], ['position' => 'ASC']);
        $data = [];

        foreach ($categories as $cat) {
            $data[] = [
                'id' => $cat->getId(),
                'name' => $cat->getName(),
                'slug' => $cat->getSlug(),
                'description' => $cat->getDescription(),
                'thread_count' => count($cat->getThreads()),
            ];
        }

        return $this->json($data);
    }

    #[Route('/category/{slug}/threads', name: 'api_forum_category_threads', methods: ['GET'])]
    public function threads(ForumCategory $category, ForumThreadRepository $threadRepo): JsonResponse
    {
        $threads = $threadRepo->findBy(['category' => $category, 'deletedAt' => null], ['createdAt' => 'DESC']);
        $data = [];

        foreach ($threads as $t) {
            $data[] = [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'slug' => $t->getSlug(),
                'author' => $t->getAuthor()->getUsername(),
                'views' => $t->getViews(),
                'replies' => count($t->getPosts()),
                'created_at' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $this->json($data);
    }

    #[Route('/thread/{slug}', name: 'api_forum_thread_detail', methods: ['GET'])]
    public function threadDetail(ForumThread $thread): JsonResponse
    {
        if ($thread->isDeleted()) {
            return $this->json(['error' => 'Thread not found'], 404);
        }

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
    }
}
