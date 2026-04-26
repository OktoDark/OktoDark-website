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

use App\Pagination\Paginator;
use App\Repository\ForumThreadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ForumSearchController extends AbstractController
{
    #[Route('/forum/search', name: 'forum_search', methods: ['GET'])]
    public function search(Request $request, ForumThreadRepository $threadRepository): Response
    {
        $query = $request->query->get('q', '');
        $page = $request->query->getInt('page', 1);

        $qb = $threadRepository->createQueryBuilder('t')
            ->where('t.deletedAt IS NULL');

        if ($query) {
            $qb->andWhere('t.title LIKE :query OR t.content LIKE :query')
               ->setParameter('query', '%'.$query.'%');
        }

        // Optional filter by author
        if ($author = $request->query->get('author')) {
            $qb->join('t.author', 'u')
               ->andWhere('u.username = :author')
               ->setParameter('author', $author);
        }

        $qb->orderBy('t.createdAt', 'DESC');

        $paginator = (new Paginator($qb, 15))->paginate($page);

        return $this->render('@theme/forum/search.html.twig', [
            'query' => $query,
            'paginator' => $paginator,
        ]);
    }
}
