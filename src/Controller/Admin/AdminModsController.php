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

use App\Entity\ModCategory;
use App\Entity\Mods;
use App\Form\ModsType;
use App\Repository\ModCategoryRepository;
use App\Repository\ModsRepository;
use App\Security\Attribute\Permission;
use App\Service\ImageResizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/mods')]
#[Permission('admin.mods.index', group: 'Admin', label: 'View Mods')]
class AdminModsController extends AbstractController
{
    private const BASE_UPLOAD_DIR = '/public/uploads/mods';

    public function __construct(
        private SluggerInterface $slugger,
        private ParameterBagInterface $parameterBag,
        private Filesystem $filesystem,
        private ValidatorInterface $validator,
        private ImageResizer $imageResizer,
    ) {
    }

    #[Route('/', name: 'admin_mods_index', methods: ['GET'])]
    public function index(ModsRepository $modsRepository, ModCategoryRepository $categoryRepo): Response
    {
        return $this->render('@theme/admin/mods/index.html.twig', [
            'mods' => $modsRepository->findAllWithAuthor(),
            'categories' => $categoryRepo->findAll(),
        ]);
    }

    private function getFormErrors(Form $form): array
    {
        $errors = ['_global' => [], 'fields' => []];
        $this->extractFormErrors($form, $errors);

        return $errors;
    }

    private function extractFormErrors(Form $form, array &$errors): void
    {
        foreach ($form->getErrors() as $error) {
            if ($form->isRoot()) {
                $errors['_global'][] = $error->getMessage();
            } else {
                $path = [];
                $parent = $form;
                while (null !== $parent) {
                    if (!$parent->isRoot()) {
                        $path[] = $parent->getName();
                    }
                    $parent = $parent->getParent();
                }
                $path = array_reverse($path);
                $key = implode('_', $path);
                $errors['fields'][$key][] = $error->getMessage();
            }
        }

        foreach ($form->all() as $child) {
            $this->extractFormErrors($child, $errors);
        }
    }

    #[Route('/new', name: 'admin_mods_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $mod = new Mods();
        $mod->setAuthor($this->getUser());

        $form = $this->createForm(ModsType::class, $mod, [
            'action' => $this->generateUrl('admin_mods_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($mod->getName()) {
                $mod->setShortNameSlug($this->slugger->slug($mod->getName())->lower());
            }

            if ($form->isValid()) {
                $baseDir = $this->parameterBag->get('kernel.project_dir')
                    .self::BASE_UPLOAD_DIR.'/'.$mod->getShortNameSlug();

                $bannerDir = $baseDir.'/banner';
                $thumbDir = $baseDir.'/thumbnail';
                $galleryDir = $baseDir.'/gallery';

                $this->filesystem->mkdir([$bannerDir, $thumbDir, $galleryDir]);

                // BANNER
                /** @var UploadedFile|null $bannerFile */
                $bannerFile = $form->get('bannerFile')->getData();

                if ($bannerFile instanceof UploadedFile && $bannerFile->isValid()) {
                    $extension = $bannerFile->guessExtension();
                    $bannerName = $mod->getShortNameSlug().'-banner.'.$extension;

                    try {
                        $bannerFile->move($bannerDir, $bannerName);
                    } catch (FileException $e) {
                        return new JsonResponse(['success' => false, 'message' => 'Banner upload failed'], 500);
                    }

                    $mod->setBannerImage($bannerName);

                    // Thumbnail
                    $thumbName = $mod->getShortNameSlug().'-thumb.'.$extension;
                    $this->imageResizer->resize(
                        $bannerDir.'/'.$bannerName,
                        $thumbDir.'/'.$thumbName,
                        300
                    );
                    $mod->setThumbnail($thumbName);
                }

                // GALLERY
                /** @var UploadedFile[] $galleryFiles */
                $galleryFiles = $form->get('galleryFiles')->getData();
                $currentGallery = $mod->getGallery();

                foreach ($galleryFiles as $file) {
                    if (!$file instanceof UploadedFile || !$file->isValid()) {
                        continue;
                    }

                    $filename = $mod->getShortNameSlug().'-gallery-'.uniqid().'.'.$file->guessExtension();
                    $file->move($galleryDir, $filename);

                    $currentGallery[] = $filename;

                    $smallName = 'small-'.$filename;
                    $this->imageResizer->resize(
                        $galleryDir.'/'.$filename,
                        $galleryDir.'/'.$smallName,
                        300
                    );
                }

                $mod->setGallery($currentGallery);

                $em->persist($mod);
                $em->flush();

                return new JsonResponse(['success' => true, 'message' => 'Mod added successfully!']);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->getFormErrors($form),
            ], 400);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('@theme/admin/mods/_form.html.twig', [
                'form' => $form->createView(),
                'mod' => $mod,
            ]);
        }

        return $this->redirectToRoute('admin_mods_index');
    }

    #[Route('/{id}/edit', name: 'admin_mods_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, ModsRepository $modsRepository, EntityManagerInterface $em): Response
    {
        $mod = $modsRepository->findWithAuthor($id);

        if (!$mod) {
            throw $this->createNotFoundException('Mod not found');
        }

        $form = $this->createForm(ModsType::class, $mod, [
            'action' => $this->generateUrl('admin_mods_edit', ['id' => $mod->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($mod->getName()) {
                $mod->setShortNameSlug($this->slugger->slug($mod->getName())->lower());
            }

            if ($form->isValid()) {
                $baseDir = $this->parameterBag->get('kernel.project_dir')
                    .self::BASE_UPLOAD_DIR.'/'.$mod->getShortNameSlug();

                $bannerDir = $baseDir.'/banner';
                $thumbDir = $baseDir.'/thumbnail';
                $galleryDir = $baseDir.'/gallery';

                $this->filesystem->mkdir([$bannerDir, $thumbDir, $galleryDir]);

                // BANNER UPDATE
                /** @var UploadedFile|null $bannerFile */
                $bannerFile = $form->get('bannerFile')->getData();

                if ($bannerFile instanceof UploadedFile && $bannerFile->isValid()) {
                    if ($mod->getBannerImage()) {
                        $old = $bannerDir.'/'.$mod->getBannerImage();
                        if ($this->filesystem->exists($old)) {
                            $this->filesystem->remove($old);
                        }
                    }

                    $extension = $bannerFile->guessExtension();
                    $bannerName = $mod->getShortNameSlug().'-banner.'.$extension;
                    $bannerFile->move($bannerDir, $bannerName);
                    $mod->setBannerImage($bannerName);

                    $thumbName = $mod->getShortNameSlug().'-thumb.'.$extension;
                    $this->imageResizer->resize(
                        $bannerDir.'/'.$bannerName,
                        $thumbDir.'/'.$thumbName,
                        300
                    );
                    $mod->setThumbnail($thumbName);
                }

                // GALLERY UPDATE
                /** @var UploadedFile[] $galleryFiles */
                $galleryFiles = $form->get('galleryFiles')->getData();
                $currentGallery = $mod->getGallery();

                foreach ($galleryFiles as $file) {
                    if (!$file instanceof UploadedFile || !$file->isValid()) {
                        continue;
                    }

                    $filename = $mod->getShortNameSlug().'-gallery-'.uniqid().'.'.$file->guessExtension();
                    $file->move($galleryDir, $filename);
                    $currentGallery[] = $filename;

                    $smallName = 'small-'.$filename;
                    $this->imageResizer->resize(
                        $galleryDir.'/'.$filename,
                        $galleryDir.'/'.$smallName,
                        300
                    );
                }

                $mod->setGallery($currentGallery);

                $em->persist($mod);
                $em->flush();

                return new JsonResponse(['success' => true, 'message' => 'Mod updated successfully!']);
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->getFormErrors($form),
            ], 400);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('@theme/admin/mods/_form.html.twig', [
                'form' => $form->createView(),
                'mod' => $mod,
            ]);
        }

        return $this->redirectToRoute('admin_mods_index');
    }

    #[Route('/{id}', name: 'admin_mods_delete', methods: ['POST'])]
    public function delete(Request $request, Mods $mod, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$mod->getId(), $request->request->get('_token'))) {
            $baseDir = $this->parameterBag->get('kernel.project_dir')
                .self::BASE_UPLOAD_DIR.'/'.$mod->getShortNameSlug();

            if ($this->filesystem->exists($baseDir)) {
                $this->filesystem->remove($baseDir);
            }

            $em->remove($mod);
            $em->flush();

            $this->addFlash('success', 'Mod deleted successfully!');
        }

        return $this->redirectToRoute('admin_mods_index');
    }

    #[Route('/gallery/delete', name: 'admin_mods_gallery_delete', methods: ['POST'])]
    public function galleryDelete(Request $request, ModsRepository $modsRepository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['path'])) {
            return new JsonResponse(['success' => false, 'message' => 'Missing path'], 400);
        }

        $fullPath = $data['path'];
        $modId = $data['modId'] ?? null;
        $filename = $data['filename'] ?? null;

        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $realPath = $projectDir.'/public'.parse_url($fullPath, \PHP_URL_PATH);

        if ($this->filesystem->exists($realPath)) {
            $this->filesystem->remove($realPath);
        }

        $pathInfo = pathinfo($realPath);
        $smallPath = $pathInfo['dirname'].'/small-'.$pathInfo['basename'];
        if ($this->filesystem->exists($smallPath)) {
            $this->filesystem->remove($smallPath);
        }

        if ($modId && $filename) {
            $mod = $modsRepository->find($modId);
            if ($mod) {
                $mod->removeGalleryImage($filename);
                $em->flush();
            }
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/gallery/reorder', name: 'admin_mods_gallery_reorder', methods: ['POST'])]
    public function galleryReorder(Request $request, ModsRepository $modsRepository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['modId'], $data['order']) || !\is_array($data['order'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid payload'], 400);
        }

        $mod = $modsRepository->find($data['modId']);
        if (!$mod) {
            return new JsonResponse(['success' => false, 'message' => 'Mod not found'], 404);
        }

        $mod->setGallery($data['order']);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    // ============================
    // CATEGORY MANAGEMENT
    // ============================
    #[Route('/categories/manager', name: 'admin_mod_category_manager')]
    public function categoryManager(ModCategoryRepository $repo): Response
    {
        return $this->render('@theme/admin/mods/manager.html.twig', [
            'categories' => $repo->findAll(),
        ]);
    }

    #[Route('/categories/create', name: 'admin_mod_category_create', methods: ['POST'])]
    public function categoryCreate(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $name = mb_trim($request->request->get('name'));
        $imageFile = $request->files->get('image');

        if (!$name) {
            return new JsonResponse(['success' => false, 'message' => 'Name required']);
        }

        $category = new ModCategory();
        $category->setName($name);

        // Handle image upload
        if ($imageFile) {
            $uploadDir = $this->parameterBag->get('kernel.project_dir').'/public/uploads/categories';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = uniqid('cat_').'.'.$imageFile->guessExtension();

            try {
                $imageFile->move($uploadDir, $filename);
                $category->setImage($filename);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'Image upload failed']);
            }
        }

        $em->persist($category);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $category->getId(),
            'name' => $category->getName(),
            'image' => $category->getImage(),
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'admin_mod_category_edit', methods: ['POST'])]
    public function categoryEdit(ModCategory $category, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $name = mb_trim($request->request->get('name'));
        $imageFile = $request->files->get('image');

        if (!$name) {
            return new JsonResponse(['success' => false, 'message' => 'Name required']);
        }

        $category->setName($name);

        // Handle image update
        if ($imageFile) {
            $uploadDir = $this->parameterBag->get('kernel.project_dir').'/public/uploads/categories';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Remove old image
            if ($category->getImage()) {
                $oldPath = $uploadDir.'/'.$category->getImage();
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $filename = uniqid('cat_').'.'.$imageFile->guessExtension();

            try {
                $imageFile->move($uploadDir, $filename);
                $category->setImage($filename);
            } catch (\Exception $e) {
                return new JsonResponse(['success' => false, 'message' => 'Image upload failed']);
            }
        }

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'name' => $category->getName(),
            'image' => $category->getImage(),
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'admin_mod_category_delete', methods: ['POST'])]
    public function categoryDelete(ModCategory $category, EntityManagerInterface $em): JsonResponse
    {
        // Delete image file
        if ($category->getImage()) {
            $uploadDir = $this->parameterBag->get('kernel.project_dir').'/public/uploads/categories';
            $path = $uploadDir.'/'.$category->getImage();

            if (file_exists($path)) {
                unlink($path);
            }
        }

        $em->remove($category);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
