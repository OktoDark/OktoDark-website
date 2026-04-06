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

use App\Entity\Comment;
use App\Entity\Post;
use App\Event\CommentCreatedEvent;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    #[Route('/', name: 'blog', defaults: ['page' => 1, '_format' => 'html'], methods: ['GET'])]
    #[Route('/rss.xml', name: 'blog_rss', defaults: ['page' => 1, '_format' => 'xml'], methods: ['GET'])]
    #[Route('/page/{page<[1-9]\d*>}', name: 'blog_index_paginated', defaults: ['_format' => 'html'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Cache(smaxage: 10)]
    public function index(
        Request $request,
        int $page,
        string $_format,
        PostRepository $posts,
        TagRepository $tags,
    ): Response {
        $tag = null;

        if ($request->query->has('tag')) {
            $tag = $tags->findOneBy(['name' => $request->query->get('tag')]);
        }

        $latestPosts = $posts->findLatest($page, $tag);

        return $this->render("@theme/blog/index.$_format.twig", [
            'paginator' => $latestPosts,
            'tagName' => $tag?->getName(),
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/posts/{slug}', methods: ['GET'], name: 'blog_post')]
    public function postShow(
        #[MapEntity(mapping: ['slug' => 'slug'])] Post $post,
    ): Response {
        return $this->render('@theme/blog/post_show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/new', name: 'blog_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $post = new Post();
        $post->setAuthor($this->getUser());

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post->setAuthor($this->getUser());
            $em->persist($post);
            $em->flush();

            return $this->redirectToRoute('blog_post', ['slug' => $post->getSlug()]);
        }

        return $this->render('@theme/blog/post_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/edit/{slug}', name: 'blog_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        #[MapEntity(mapping: ['slug' => 'slug'])] Post $post,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('blog_post', ['slug' => $post->getSlug()]);
        }

        return $this->render('@theme/blog/post_edit.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete/{slug}', name: 'blog_delete', methods: ['POST', 'GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        #[MapEntity(mapping: ['slug' => 'slug'])] Post $post,
        EntityManagerInterface $em,
    ): Response {
        $em->remove($post);
        $em->flush();

        return $this->redirectToRoute('blog');
    }

    #[Route('/comment/{postSlug}/new', methods: ['POST'], name: 'comment_new')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function commentNew(
        string $postSlug,
        Request $request,
        PostRepository $posts,
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface $entityManager,
    ): Response {
        $post = $posts->findOneBy(['slug' => $postSlug]);

        if (!$post) {
            throw $this->createNotFoundException('Post not found.');
        }

        $comment = new Comment();
        $comment->setAuthor($this->getUser());
        $post->addComment($comment);

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($comment);
            $entityManager->flush();

            $eventDispatcher->dispatch(new CommentCreatedEvent($comment));

            return $this->redirectToRoute('blog_post', [
                'slug' => $post->getSlug(),
            ]);
        }

        return $this->render('@theme/blog/comment_form_error.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    public function commentForm(Post $post): Response
    {
        $form = $this->createForm(CommentType::class, new Comment());

        return $this->render('@theme/blog/_comment_form.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/search', methods: ['GET'], name: 'blog_search')]
    public function search(Request $request, PostRepository $posts): Response
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('l', 10);

        if (!$request->isXmlHttpRequest()) {
            return $this->render('blog/search.html.twig', [
                'query' => $query,
            ]);
        }

        $foundPosts = $posts->findBySearchQuery($query, $limit);

        $results = array_map(function (Post $post) {
            return [
                'title' => htmlspecialchars($post->getTitle(), ENT_COMPAT | ENT_HTML5),
                'date' => $post->getPublishedAt()->format('M d, Y'),
                'author' => htmlspecialchars($post->getAuthor()->getFullName(), ENT_COMPAT | ENT_HTML5),
                'summary' => htmlspecialchars($post->getSummary(), ENT_COMPAT | ENT_HTML5),
                'url' => $this->generateUrl('blog_post', ['slug' => $post->getSlug()]),
            ];
        }, $foundPosts);

        return $this->json($results);
    }
}
