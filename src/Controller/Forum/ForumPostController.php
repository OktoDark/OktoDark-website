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

use App\Entity\ForumPost;
use App\Entity\ForumReaction;
use App\Entity\User;
use App\Form\Forum\ForumPostType;
use App\Repository\ForumReactionRepository;
use App\Security\Attribute\Permission;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forum/post')]
#[Permission('forum.use.post', group: 'Forum', label: 'Use post features')]
final class ForumPostController extends AbstractController
{
    /**
     * Allow editing a post with authorization checks and content validation.
     */
    #[Route('/edit/{id}', name: 'forum_post_edit', methods: ['GET', 'POST'])]
    #[Permission('forum.edit.post', group: 'Forum', label: 'Edit forum posts')]
    public function edit(
        ForumPost $post,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($post->isDeleted()) {
            throw $this->createNotFoundException('Post not found.');
        }

        if ($post->getAuthor() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ForumPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ('' === mb_trim($post->getContent())) {
                $this->addFlash('error', 'Reply cannot be empty.');

                return $this->redirectToRoute('forum_thread_view', [
                    'slug' => $post->getThread()->getSlug(),
                ]);
            }

            $post->setUpdatedAt(new \DateTime());
            $post->setEditedBy($currentUser);
            $em->flush();

            return $this->redirectToRoute('forum_thread_view', [
                'slug' => $post->getThread()->getSlug(),
            ]);
        }

        return $this->render('@theme/forum/edit_post.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Soft-delete a forum post after ownership or admin authorization.
     */
    #[Route('/delete/{id}', name: 'forum_post_delete', methods: ['POST'])]
    #[Permission('forum.delete.post', group: 'Forum', label: 'Delete forum posts')]
    public function delete(ForumPost $post, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($post->getAuthor() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $threadSlug = $post->getThread()->getSlug();

        // Soft delete
        $post->setDeletedAt(new \DateTime());
        $em->flush();

        $this->addFlash('success', 'Post deleted.');

        return $this->redirectToRoute('forum_thread_view', ['slug' => $threadSlug]);
    }

    /**
     * Mark a post as reported and notify moderators.
     */
    #[Route('/report/{id}', name: 'forum_post_report', methods: ['POST'])]
    #[Permission('forum.report.post', group: 'Forum', label: 'Report forum posts')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function report(ForumPost $post, EntityManagerInterface $em): Response
    {
        if ($post->isDeleted()) {
            throw $this->createNotFoundException('Post not found.');
        }

        $post->setReported(true);
        $em->flush();

        $this->addFlash('success', 'Post reported to moderators.');

        return $this->redirectToRoute('forum_thread_view', [
            'slug' => $post->getThread()->getSlug(),
        ]);
    }

    /**
     * Mark a post as the accepted answer for its thread, adjust reputation, and trigger badge checks.
     */
    #[Route('/accept/{id}', name: 'forum_post_accept', methods: ['POST'])]
    #[Permission('forum.accept.answer', group: 'Forum', label: 'Accept forum answers')]
    public function acceptAnswer(ForumPost $post, EntityManagerInterface $em, BadgeService $badgeService): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $thread = $post->getThread();

        if ($thread->getAuthor() !== $currentUser && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($post->isDeleted()) {
            throw $this->createNotFoundException();
        }

        // Unset any previously accepted answer in this thread
        foreach ($thread->getPosts() as $p) {
            if ($p->isAcceptedAnswer()) {
                $p->setIsAcceptedAnswer(false);
                $p->getAuthor()->addReputation(-15);
            }
        }

        $post->setIsAcceptedAnswer(true);
        $post->getAuthor()->addReputation(15);

        // Mark thread as resolved if it's a question
        if ('question' === $thread->getType()) {
            $thread->setIsResolved(true);
        }

        $em->flush();

        $badgeService->checkAutomatedBadges($post->getAuthor());

        $this->addFlash('success', 'Post marked as accepted answer.');

        return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
    }

    /**
     * Toggle upvote/downvote reactions on a post and update author reputation.
     */
    #[Route('/react/{id}/{type}', name: 'forum_post_react', methods: ['POST'])]
    #[Permission('forum.react.post', group: 'Forum', label: 'React to forum posts')]
    public function react(
        ForumPost $post,
        string $type,
        EntityManagerInterface $em,
        ForumReactionRepository $reactionRepository,
        BadgeService $badgeService,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($post->isDeleted()) {
            return $this->json(['error' => 'Post not found.'], 404);
        }

        if ($post->getAuthor() === $currentUser) {
            return $this->json(['error' => 'You cannot react to your own post.'], 403);
        }

        if (!\in_array($type, ['upvote', 'downvote'], true)) {
            return $this->json(['error' => 'Invalid reaction type.'], 400);
        }

        $existing = $reactionRepository->findOneBy([
            'post' => $post,
            'user' => $currentUser,
        ]);

        $author = $post->getAuthor();

        if ($existing) {
            // Remove old impact
            if ('upvote' === $existing->getType()) {
                $author->addReputation(-1);
            } else {
                $author->addReputation(1);
            }

            if ($existing->getType() === $type) {
                // Toggled off
                $em->remove($existing);
                $em->flush();

                return $this->redirectToRoute('forum_thread_view', ['slug' => $post->getThread()->getSlug()]);
            }

            // Switch type
            $existing->setType($type);
        } else {
            $reaction = new ForumReaction();
            $reaction->setPost($post);
            $reaction->setUser($currentUser);
            $reaction->setType($type);
            $em->persist($reaction);
        }

        // Apply new impact
        if ('upvote' === $type) {
            $author->addReputation(1);
        } else {
            $author->addReputation(-1);
        }

        $em->flush();

        $badgeService->checkAutomatedBadges($author);

        return $this->redirectToRoute('forum_thread_view', ['slug' => $post->getThread()->getSlug()]);
    }
}
