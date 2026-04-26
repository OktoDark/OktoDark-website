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

use App\Entity\ForumCategory;
use App\Form\ForumCategoryType;
use App\Repository\ForumCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/forum/category')]
#[IsGranted('ROLE_ADMIN')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private ForumCategoryRepository $repo,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('/', name: 'admin_forum_categories')]
    public function index(): Response
    {
        return $this->render('@theme/admin/forum/categories.html.twig', [
            'categories' => $this->repo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/create', name: 'admin_forum_category_create')]
    public function create(Request $request): Response
    {
        $category = new ForumCategory();
        $form = $this->createForm(ForumCategoryType::class, $category);

        $form->handleRequest($request);
        
        // BUG FIX: Generate slug BEFORE isValid() so validation passes
        if ($form->isSubmitted() && !$category->getSlug() && $category->getName()) {
            $slugger = new AsciiSlugger();
            $category->setSlug(strtolower($slugger->slug($category->getName())));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();

            $this->addFlash('success', 'Category created successfully.');
            return $this->redirectToRoute('admin_forum_categories');
        }

        return $this->render('@theme/admin/forum/category_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Create Category',
        ]);
    }

    #[Route('/edit/{id}', name: 'admin_forum_category_edit')]
    public function edit(
        ForumCategory $category,
        Request $request
    ): Response {
        $form = $this->createForm(ForumCategoryType::class, $category);

        $form->handleRequest($request);

        // BUG FIX: Generate slug BEFORE isValid() so validation passes
        if ($form->isSubmitted() && !$category->getSlug() && $category->getName()) {
            $slugger = new AsciiSlugger();
            $category->setSlug(strtolower($slugger->slug($category->getName())));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Category updated successfully.');
            return $this->redirectToRoute('admin_forum_categories');
        }

        return $this->render('@theme/admin/forum/category_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit Category',
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_forum_category_delete', methods: ['POST'])]
    public function delete(
        ForumCategory $category
    ): Response {
        $this->em->remove($category);
        $this->em->flush();

        $this->addFlash('success', 'Category deleted.');
        return $this->redirectToRoute('admin_forum_categories');
    }
}
