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
use App\Entity\User;
use App\Event\CommentCreatedEvent;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    public function __construct(private ParameterBagInterface $parameterBag)
    {
    }

    #[Route('/', name: 'blog', defaults: ['page' => 1, '_format' => 'html'], methods: ['GET'])]
    #[Route('/rss.xml', name: 'blog_rss', defaults: ['page' => 1, '_format' => 'xml'], methods: ['GET'])]
    #[Route('/page/{page<[1-9]\d*>}', name: 'blog_index_paginated', defaults: ['_format' => 'html'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
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
            'tags' => $tags->findAll(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/posts/{slug}', name: 'blog_post', methods: ['GET'])]
    public function postShow(
        #[MapEntity(mapping: ['slug' => 'slug'])] Post $post,
        PostRepository $posts,
    ): Response {
        return $this->render('@theme/blog/post_show.html.twig', [
            'post' => $post,
            'relatedPosts' => $posts->findRelated($post),
        ]);
    }

    #[Route('/new', name: 'blog_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $post = new Post();
        $post->setAuthor($user);

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $featuredImageFile */
            $featuredImageFile = $form->get('featuredImage')->getData();

            if ($featuredImageFile) {
                $originalFilename = pathinfo($featuredImageFile->getClientOriginalName(), \PATHINFO_FILENAME);
                $newFilename = $originalFilename.'-'.uniqid().'.'.$featuredImageFile->guessExtension();

                try {
                    $featuredImageFile->move(
                        $this->parameterBag->get('kernel.project_dir').'/public/uploads/blog_images',
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                    $this->addFlash('error', 'Could not upload featured image: '.$e->getMessage());

                    return $this->redirectToRoute('blog_new');
                }
                $post->setFeaturedImage('/uploads/blog_images/'.$newFilename);
            }

            $post->setAuthor($user);
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
        // Store the old featured image path if it exists
        $oldFeaturedImage = $post->getFeaturedImage();

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $featuredImageFile */
            $featuredImageFile = $form->get('featuredImage')->getData();

            if ($featuredImageFile) {
                $originalFilename = pathinfo($featuredImageFile->getClientOriginalName(), \PATHINFO_FILENAME);
                $newFilename = $originalFilename.'-'.uniqid().'.'.$featuredImageFile->guessExtension();

                try {
                    $featuredImageFile->move(
                        $this->parameterBag->get('kernel.project_dir').'/public/uploads/blog_images',
                        $newFilename
                    );
                    // Remove the old file if a new one is uploaded and an old one existed
                    if ($oldFeaturedImage && file_exists($this->parameterBag->get('kernel.project_dir').'/public'.$oldFeaturedImage)) {
                        unlink($this->parameterBag->get('kernel.project_dir').'/public'.$oldFeaturedImage);
                    }
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                    $this->addFlash('error', 'Could not upload featured image: '.$e->getMessage());

                    return $this->redirectToRoute('blog_edit', ['slug' => $post->getSlug()]);
                }
                $post->setFeaturedImage('/uploads/blog_images/'.$newFilename);
            } elseif ($oldFeaturedImage) {
                // If no new file is uploaded, but there was an old one, keep the old one
                $post->setFeaturedImage($oldFeaturedImage);
            } else {
                // If no new file is uploaded and no old one existed, set to null
                $post->setFeaturedImage(null);
            }

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
        // Remove the featured image file if it exists
        if ($post->getFeaturedImage()) {
            $imagePath = $this->parameterBag->get('kernel.project_dir').'/public'.$post->getFeaturedImage();
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $em->remove($post);
        $em->flush();

        return $this->redirectToRoute('blog');
    }

    #[Route('/comment/{postSlug}/new', name: 'comment_new', methods: ['POST'])]
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

        /** @var User $user */
        $user = $this->getUser();

        $comment = new Comment();
        $comment->setAuthor($user);
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

    #[Route('/search', name: 'blog_search', methods: ['GET'])]
    public function search(Request $request, PostRepository $posts): Response
    {
        $query = (string) $request->query->get('q', '');
        $limit = (int) $request->query->get('l', 10);

        if ($request->isXmlHttpRequest()) {
            $foundPosts = $posts->findBySearchQuery($query, $limit);

            $results = [];
            foreach ($foundPosts as $post) {
                $results[] = [
                    'title' => htmlspecialchars($post->getTitle(), \ENT_COMPAT | \ENT_HTML5),
                    'date' => $post->getPublishedAt()->format('M d, Y'),
                    'author' => htmlspecialchars($post->getAuthor()->getFullName(), \ENT_COMPAT | \ENT_HTML5),
                    'summary' => htmlspecialchars($post->getSummary(), \ENT_COMPAT | \ENT_HTML5),
                    'url' => $this->generateUrl('blog_post', ['slug' => $post->getSlug()]),
                ];
            }

            return $this->json($results);
        }

        $foundPosts = null;
        if ('' !== $query) {
            $foundPosts = $posts->findBySearchQuery($query, $limit);
        }

        return $this->render('@theme/blog/search.html.twig', [
            'query' => $query,
            'posts' => $foundPosts,
        ]);
    }
}
