<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Forum;

use App\Entity\ForumCategory;
use App\Pagination\Paginator;
use App\Repository\BadgeRepository;
use App\Repository\ForumCategoryRepository;
use App\Repository\ForumPostRepository;
use App\Repository\ForumThreadRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum')]
class ForumCategoryController extends AbstractController
{
    #[Route('', name: 'forum', methods: ['GET'])]
    public function index(
        ForumCategoryRepository $categoryRepository,
        ForumThreadRepository $threadRepository,
        ForumPostRepository $postRepository,
        UserRepository $userRepository,
        BadgeRepository $badgeRepository,
    ): Response {
        // Only get top-level categories
        $categories = $categoryRepository->findBy(['parent' => null], ['position' => 'ASC']);

        return $this->render('@theme/forum/index.html.twig', [
            'categories' => $categories,
            'totalThreads' => $threadRepository->count([]),
            'totalPosts' => $postRepository->count([]),
            'onlineUsers' => $userRepository->findActiveUsers(),
            'latestThreads' => $threadRepository->findLatest(5),
            'permanentBadges' => $badgeRepository->findBy(['isPermanent' => true], ['name' => 'ASC']),
        ]);
    }

    #[Route('/category/{slug}', name: 'forum_category_view', methods: ['GET'])]
    public function view(
        #[MapEntity(mapping: ['slug' => 'slug'])] ForumCategory $category,
        Request $request,
        ForumThreadRepository $threadRepository,
    ): Response {
        $page = $request->query->getInt('page', 1);
        $sort = $request->query->get('sort', 'updated');

        $queryBuilder = $threadRepository->createQueryBuilder('t')
            ->where('t.category = :category')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('category', $category)
            ->orderBy('t.pinned', 'DESC');

        switch ($sort) {
            case 'newest':
                $queryBuilder->addOrderBy('t.createdAt', 'DESC');
                break;
            case 'oldest':
                $queryBuilder->addOrderBy('t.createdAt', 'ASC');
                break;
            case 'views':
                $queryBuilder->addOrderBy('t.views', 'DESC');
                break;
            case 'replies':
                $queryBuilder->leftJoin('t.posts', 'p')
                    ->groupBy('t.id')
                    ->addOrderBy('COUNT(p.id)', 'DESC');
                break;
            case 'updated':
            default:
                $queryBuilder->addOrderBy('t.updatedAt', 'DESC');
                $sort = 'updated';
                break;
        }

        $paginator = (new Paginator($queryBuilder, 15))->paginate($page);

        return $this->render('@theme/forum/category.html.twig', [
            'category' => $category,
            'paginator' => $paginator,
            'currentSort' => $sort,
        ]);
    }
}
