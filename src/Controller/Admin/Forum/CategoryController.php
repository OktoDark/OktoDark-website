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
use App\Form\Forum\ForumCategoryType;
use App\Repository\ForumCategoryRepository;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/forum/category')]
final class CategoryController extends AbstractController
{
    /**
     * Initialize category repository and entity manager for forum category management.
     */
    public function __construct(
        private ForumCategoryRepository $repo,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Render the forum category listing ordered by position.
     */
    #[Route('/', name: 'admin_forum_categories')]
    #[Permission('admin.forum.categories.index')]
    public function index(): Response
    {
        return $this->render('@theme/admin/forum/categories.html.twig', [
            'categories' => $this->repo->findBy([], ['position' => 'ASC']),
        ]);
    }

    /**
     * Create a new forum category using the dedicated form type.
     */
    #[Route('/create', name: 'admin_forum_category_create')]
    #[Permission('admin.forum.categories.create')]
    public function create(Request $request): Response
    {
        $category = new ForumCategory();
        $form = $this->createForm(ForumCategoryType::class, $category);

        $form->handleRequest($request);

        // BUG FIX: Generate slug BEFORE isValid() so validation passes
        if ($form->isSubmitted() && !$category->getSlug() && $category->getName()) {
            $slugger = new AsciiSlugger();
            $category->setSlug(mb_strtolower($slugger->slug($category->getName())));
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

    /**
     * Edit an existing forum category and update its slug if missing.
     */
    #[Route('/edit/{id}', name: 'admin_forum_category_edit')]
    #[Permission('admin.forum.categories.edit')]
    public function edit(
        ForumCategory $category,
        Request $request,
    ): Response {
        $form = $this->createForm(ForumCategoryType::class, $category);

        $form->handleRequest($request);

        // BUG FIX: Generate slug BEFORE isValid() so validation passes
        if ($form->isSubmitted() && !$category->getSlug() && $category->getName()) {
            $slugger = new AsciiSlugger();
            $category->setSlug(mb_strtolower($slugger->slug($category->getName())));
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

    /**
     * Permanently delete a forum category.
     */
    #[Route('/delete/{id}', name: 'admin_forum_category_delete', methods: ['POST'])]
    #[Permission('admin.forum.categories.delete')]
    public function delete(
        ForumCategory $category,
    ): Response {
        $this->em->remove($category);
        $this->em->flush();

        $this->addFlash('success', 'Category deleted.');

        return $this->redirectToRoute('admin_forum_categories');
    }
}
