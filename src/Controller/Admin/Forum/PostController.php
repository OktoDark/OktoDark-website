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

use App\Entity\ForumPost;
use App\Entity\User;
use App\Repository\ForumPostRepository;
use App\Service\ForumModeratorActionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/forum/post')]
#[IsGranted('ROLE_ADMIN')]
final class PostController extends AbstractController
{
    public function __construct(
        private ForumPostRepository $postRepo,
        private EntityManagerInterface $em,
        private ForumModeratorActionService $moderatorLogger,
    ) {
    }

    #[Route('/', name: 'admin_forum_posts')]
    public function index(Request $request): Response
    {
        $reported = $request->query->get('reported');
        $username = $request->query->get('author');

        $qb = $this->postRepo->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC');

        if ($reported) {
            $qb->andWhere('p.reported = true');
        }

        if ($username) {
            $qb->join('p.author', 'u')
                ->andWhere('u.username LIKE :user')
                ->setParameter('user', '%'.$username.'%');
        }

        return $this->render('@theme/admin/forum/posts.html.twig', [
            'posts' => $qb->getQuery()->getResult(),
            'currentFilters' => [
                'reported' => $reported,
                'author' => $username,
            ],
        ]);
    }

    #[Route('/reports', name: 'admin_forum_reports')]
    public function reports(): Response
    {
        // Alias for index with reported=1
        return $this->redirectToRoute('admin_forum_posts', ['reported' => 1]);
    }

    #[Route('/dismiss-report/{id}', name: 'admin_forum_post_dismiss_report')]
    public function dismissReport(ForumPost $post): Response
    {
        $post->setReported(false);
        $this->em->flush();

        /** @var User $user */
        $user = $this->getUser();
        $this->moderatorLogger->log($user, 'dismiss_report', 'post', $post->getId(), 'In thread: '.$post->getThread()->getTitle());
        $this->addFlash('success', 'Report dismissed.');

        return $this->redirectToRoute('admin_forum_posts', ['reported' => 1]);
    }

    #[Route('/delete/{id}', name: 'admin_forum_post_delete', methods: ['POST'])]
    public function delete(ForumPost $post, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$post->getId(), $request->request->get('_token'))) {
            $id = $post->getId();
            $threadTitle = $post->getThread()->getTitle();
            $this->em->remove($post);
            $this->em->flush();

            /** @var User $user */
            $user = $this->getUser();
            $this->moderatorLogger->log($user, 'permanent_delete_post', 'post', $id, 'From thread: '.$threadTitle);
            $this->addFlash('success', 'Post permanently deleted.');
        }

        return $this->redirectToRoute('admin_forum_posts');
    }
}
