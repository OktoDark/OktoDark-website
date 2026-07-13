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
use App\Entity\ForumPost;
use App\Entity\ForumThread;
use App\Entity\User;
use App\Form\Forum\ForumPostType;
use App\Form\Forum\ForumThreadType;
use App\Pagination\Paginator;
use App\Repository\ForumPostRepository;
use App\Repository\UserRepository;
use App\Security\Attribute\Permission;
use App\Service\BadgeService;
use App\Service\EmailIdentityService;
use App\Service\ForumNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Route('/forum/thread')]
#[Permission('forum.view.thread', group: 'Forum', label: 'View threads')]
final class ForumThreadController extends AbstractController
{
    /**
     * Initialize thread controller dependencies for mail delivery, URL generation, translation, email identity, and Twig rendering.
     */
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private EmailIdentityService $emailIdentity,
        private Environment $twig,
    ) {
    }

    /**
     * Display a thread with paginated posts, handle new post submissions, send email notifications, parse mentions, and check automated badges.
     */
    #[Route('/view/{slug}', name: 'forum_thread_view', methods: ['GET', 'POST'])]
    public function view(
        #[MapEntity(mapping: ['slug' => 'slug'])] ForumThread $thread,
        Request $request,
        EntityManagerInterface $em,
        ForumPostRepository $postRepository,
        ForumNotificationService $forumNotifier,
        UserRepository $userRepository,
        BadgeService $badgeService,
    ): Response {
        if ($thread->isDeleted() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Thread not found.');
        }

        $thread->setViews($thread->getViews() + 1);
        $em->flush();

        /** @var User $user */
        $user = $this->getUser();

        $page = $request->query->getInt('page', 1);
        $queryBuilder = $postRepository->createQueryBuilder('p')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread)
            ->orderBy('p.createdAt', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $queryBuilder->andWhere('p.deletedAt IS NULL');
        }

        $paginator = (new Paginator($queryBuilder, 10))->paginate($page);

        $post = new ForumPost();
        $post->setThread($thread);
        $post->setAuthor($user);

        $form = $this->createForm(ForumPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($thread->isLocked()) {
                $this->addFlash('error', 'This thread is locked.');

                return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
            }

            $em->persist($post);
            $thread->setUpdatedAt(new \DateTime());
            $em->flush();

            // --- Email Notification ---
            $threadAuthor = $thread->getAuthor();
            $replier = $post->getAuthor();

            if ($threadAuthor !== $replier && $threadAuthor->getNotificationPreference('forum_email')) {
                $subject = $this->translator->trans('notification.forum_new_reply.email_subject', [], 'notifications');

                $linkToThread = $this->urlGenerator->generate(
                    'forum_thread_view',
                    ['slug' => $thread->getSlug(), '_fragment' => 'post-'.$post->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $body = $this->twig->render('emails/notifications/forum_new_reply.html.twig', [
                    'threadTitle' => $thread->getTitle(),
                    'linkToThread' => $linkToThread,
                    'threadAuthor' => $threadAuthor,
                    'replierUsername' => $replier->getUsername(),
                ]);

                $email = (new Email())
                    ->from($this->emailIdentity->noreply())
                    ->to($threadAuthor->getEmail())
                    ->subject($subject)
                    ->html($body);

                $email->getHeaders()->addTextHeader('X-Transport', 'no_reply');
                $this->mailer->send($email);
            }

            // On-site notifications + mentions
            $forumNotifier->notifyNewReply($post);
            $this->parseMentions($post->getContent(), $post, $forumNotifier, $userRepository);

            // Badges
            $badgeService->checkAutomatedBadges($user);

            return $this->redirectToRoute('forum_thread_view', [
                'slug' => $thread->getSlug(),
                'page' => $paginator->getLastPage() ?: 1,
            ]);
        }

        return $this->render('@theme/forum/thread.html.twig', [
            'thread' => $thread,
            'paginator' => $paginator,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Parse @username mentions in post content and trigger mention notifications for matched users.
     */
    private function parseMentions(string $content, ForumPost $post, ForumNotificationService $notifier, UserRepository $userRepository): void
    {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        $usernames = array_unique($matches[1]);

        foreach ($usernames as $username) {
            $mentionedUser = $userRepository->findOneBy(['username' => $username]);
            if ($mentionedUser) {
                $notifier->notifyMention($mentionedUser, $post);
            }
        }
    }

    /**
     * Create a new forum thread within a category, handling form submission, slug generation, and optional poll attachment.
     */
    #[Route('/create/{category}', name: 'forum_thread_create', methods: ['GET', 'POST'])]
    #[Permission('forum.create.category', group: 'Forum', label: 'Create threads')]
    public function create(
        ForumCategory $category,
        Request $request,
        EntityManagerInterface $em,
        BadgeService $badgeService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $thread = new ForumThread();
        $thread->setCategory($category);
        $thread->setAuthor($user);

        $form = $this->createForm(ForumThreadType::class, $thread);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slugger = new AsciiSlugger();
            $slug = mb_strtolower($slugger->slug($thread->getTitle()));

            $thread->setSlug($slug.'-'.uniqid());

            if ('' === mb_trim($thread->getContent())) {
                $this->addFlash('error', 'Thread content cannot be empty.');

                return $this->redirectToRoute('forum_thread_create', ['category' => $category->getId()]);
            }

            $poll = $thread->getPoll();
            if ($poll) {
                $poll->setThread($thread);
            }

            $em->persist($thread);
            $em->flush();

            $badgeService->checkAutomatedBadges($user);

            $this->addFlash('success', 'Thread created successfully.');

            return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
        }

        return $this->render('@theme/forum/create_thread.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit an existing thread after verifying author or admin privileges.
     */
    #[Route('/edit/{id}', name: 'forum_thread_edit', methods: ['GET', 'POST'])]
    #[Permission('forum.edit.thread', group: 'Forum', label: 'Edit threads')]
    public function edit(
        ForumThread $thread,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($thread->isDeleted()) {
            throw $this->createNotFoundException('Thread not found.');
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($thread->getAuthor() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ForumThreadType::class, $thread);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $thread->setUpdatedAt(new \DateTime());
            $em->flush();

            return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
        }

        return $this->render('@theme/forum/edit_thread.html.twig', [
            'thread' => $thread,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Toggle thread lock status and notify subscribers of status change.
     */
    #[Route('/lock/{id}', name: 'forum_thread_lock')]
    #[Permission('forum.lock.thread', group: 'Forum', label: 'Lock threads')]
    public function lock(ForumThread $thread, EntityManagerInterface $em, ForumNotificationService $notifier): Response
    {
        $thread->setLocked(!$thread->isLocked());
        $em->flush();

        if ($thread->isLocked()) {
            $notifier->notifyThreadStatusChange($thread, 'locked');
        }

        return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
    }

    /**
     * Toggle thread pinned status and notify subscribers of status change.
     */
    #[Route('/pin/{id}', name: 'forum_thread_pin')]
    #[Permission('forum.pin.thread', group: 'Forum', label: 'Pin threads')]
    public function pin(ForumThread $thread, EntityManagerInterface $em, ForumNotificationService $notifier): Response
    {
        $thread->setPinned(!$thread->isPinned());
        $em->flush();

        if ($thread->isPinned()) {
            $notifier->notifyThreadStatusChange($thread, 'pinned');
        }

        return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
    }

    /**
     * Soft-delete a thread after verifying author or admin privileges.
     */
    #[Route('/delete/{id}', name: 'forum_thread_delete', methods: ['POST'])]
    #[Permission('forum.delete.thread', group: 'Forum', label: 'Delete threads')]
    public function delete(ForumThread $thread, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($thread->getAuthor() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $categorySlug = $thread->getCategory()->getSlug();

        $thread->setDeletedAt(new \DateTime());
        $em->flush();

        $this->addFlash('success', 'Thread deleted.');

        return $this->redirectToRoute('forum_category_view', ['slug' => $categorySlug]);
    }

    /**
     * Move a thread to a different category.
     */
    #[Route('/move/{id}/{categoryId}', name: 'forum_thread_move')]
    #[Permission('forum.move.thread', group: 'Forum', label: 'Move threads')]
    public function move(
        ForumThread $thread,
        #[MapEntity(id: 'categoryId')] ForumCategory $category,
        EntityManagerInterface $em,
    ): Response {
        $thread->setCategory($category);
        $em->flush();

        $this->addFlash('success', 'Thread moved to '.$category->getName());

        return $this->redirectToRoute('forum_thread_view', ['slug' => $thread->getSlug()]);
    }
}
