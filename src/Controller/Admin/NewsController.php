<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\News;
use App\Form\NewsType;
use App\Repository\NewsRepository;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/news')]
class NewsController extends AbstractController
{
    /**
     * List all news articles ordered by most recent creation date.
     */
    #[Route('/', name: 'admin_news_index')]
    #[Permission('admin.news.index')]
    public function index(NewsRepository $repo): Response
    {
        return $this->render('@theme/admin/news/index.html.twig', [
            'news' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    /**
     * Create a new news article and redirect to the list on success.
     */
    #[Route('/new', name: 'admin_news_new')]
    #[Permission('admin.news.create')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $news = new News();
        $news->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(NewsType::class, $news);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($news);
            $em->flush();

            return $this->redirectToRoute('admin_news_index');
        }

        return $this->render('@theme/admin/news/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit an existing news article and redirect to the list on success.
     */
    #[Route('/edit/{id}', name: 'admin_news_edit')]
    #[Permission('admin.news.edit')]
    public function edit(News $news, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(NewsType::class, $news);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('admin_news_index');
        }

        return $this->render('@theme/admin/news/edit.html.twig', [
            'form' => $form->createView(),
            'news' => $news,
        ]);
    }

    /**
     * Delete a news article and redirect to the list.
     */
    #[Route('/delete/{id}', name: 'admin_news_delete')]
    #[Permission('admin.news.delete')]
    public function delete(News $news, EntityManagerInterface $em): Response
    {
        $em->remove($news);
        $em->flush();

        return $this->redirectToRoute('admin_news_index');
    }
}
