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

use App\Entity\ModRating;
use App\Entity\Mods;
use App\Form\ModsType;
use App\Repository\ModCategoryRepository;
use App\Repository\ModRatingRepository;
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

#[Permission('mods.view', group: 'Mods', label: 'View mods')]
final class ModsController extends AbstractController
{
    private const BASE_UPLOAD_DIR = '/public/uploads/mods';

    /**
     * Initializes the mods controller with required services.
     */
    public function __construct(
        private SluggerInterface $slugger,
        private ParameterBagInterface $parameterBag,
        private Filesystem $filesystem,
        private ValidatorInterface $validator,
        private ImageResizer $imageResizer,
    ) {
    }

    /**
     * Extracts form errors into a structured array with global and field-level messages.
     */
    private function getFormErrors(Form $form): array
    {
        $errors = ['_global' => [], 'fields' => []];
        $this->extractFormErrors($form, $errors);

        return $errors;
    }

    /**
     * Recursively extracts form errors into a structured array.
     */
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

    /**
     * Displays the mods listing with search, filters, and pagination.
     */
    #[Route('/mods', name: 'mods', methods: ['GET'])]
    #[Permission('mods.view', group: 'Mods', label: 'View mods')]
    public function mods(
        Request $request,
        ModsRepository $mods,
        ModCategoryRepository $modCategoryRepository,
    ): Response {
        $q = $request->query->get('q');

        // TOP 4 POPULAR CATEGORIES (with mod count)
        $topCategories = $modCategoryRepository->findTopCategoriesWithCount(4);

        // ALL CATEGORIES (with mod count)
        $allCategories = $modCategoryRepository->findAllCategoriesWithCount();

        // FILTER VALUES FROM REQUEST
        $categoryFilter = $request->query->get('category');      // single value
        $compatFilter = $request->query->get('compatibility'); // single value
        $authorFilter = $request->query->get('author');        // single value

        // FILTER OPTIONS (dropdown lists)
        $compatibilities = $mods->findAllUniqueCompatibilities();
        $authors = $mods->findAllUniqueAuthors();

        // PAGINATION
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 9;
        $offset = ($page - 1) * $limit;

        // COUNT RESULTS
        $total = $mods->countMods(
            $q,
            $categoryFilter ? [$categoryFilter] : [],
            $compatFilter ? [$compatFilter] : [],
            $authorFilter ? [$authorFilter] : []
        );

        $lastPage = max(1, (int) ceil($total / $limit));

        // SEARCH RESULTS
        $results = $mods->searchMods(
            $q,
            $limit,
            $offset,
            $categoryFilter ? [$categoryFilter] : [],
            $compatFilter ? [$compatFilter] : [],
            $authorFilter ? [$authorFilter] : []
        );

        return $this->render('@theme/mods/mods.html.twig', [
            // RESULTS
            'mods' => $results,
            'current_page' => $page,
            'last_page' => $lastPage,
            'q' => $q,

            // CATEGORY CARDS
            'topCategories' => $topCategories,
            'allCategories' => $allCategories,

            // FILTER VALUES (selected)
            'category' => $categoryFilter,
            'compatibility' => $compatFilter,
            'author' => $authorFilter,

            // FILTER OPTIONS
            'categories' => $allCategories,
            'compatibilities' => $compatibilities,
            'authors' => $authors,
        ]);
    }

    /**
     * Creates a new mod entry with banner, thumbnail, and gallery uploads.
     *
     * @return Response|JsonResponse
     */
    #[Route('/mods/new', name: 'mods_new', methods: ['GET', 'POST'])]
    #[Permission('mods.can.added', group: 'Mods', label: 'Add mods')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $mod = new Mods();
        $mod->setAuthor($this->getUser());

        $form = $this->createForm(ModsType::class, $mod, [
            'action' => $this->generateUrl('mods_new'),
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
            return $this->render('@theme/mods/_form.html.twig', [
                'form' => $form->createView(),
                'mod' => $mod,
            ]);
        }

        return $this->redirectToRoute('mods');
    }

    /**
     * Submits a rating for a mod and returns updated statistics.
     *
     * @return JsonResponse
     */
    #[Route('/mods/{id}/rate', name: 'mod_rate', options: ['expose' => true], methods: ['POST'])]
    #[Permission('mods.rate', group: 'Mods', label: 'Rate mods')]
    public function rate(
        int $id,
        Request $request,
        ModsRepository $mods,
        ModRatingRepository $ratingRepo,
        EntityManagerInterface $em,
    ): Response {
        $rating = (int) $request->request->get('rating');
        $ip = $request->getClientIp();

        // Validate 1–10 rating
        if ($rating < 1 || $rating > 10) {
            return $this->json(['error' => 'Invalid rating'], 400);
        }

        $mod = $mods->find($id);
        if (!$mod) {
            return $this->json(['error' => 'Mod not found'], 404);
        }

        // Prevent multiple ratings from same IP
        $existing = $ratingRepo->findExistingRating($mod, $ip);
        if ($existing) {
            return $this->json([
                'error' => 'already_rated',
                'message' => 'You already rated this mod.',
            ], 403);
        }

        // Save rating entry
        $entry = new ModRating();
        $entry->setMod($mod);
        $entry->setRating($rating); // now integer 1–10
        $entry->setIp($ip);

        $em->persist($entry);
        $em->flush();

        // Recalculate average rating
        $allRatings = $ratingRepo->getRatingsForMod($mod);
        $sum = 0;
        foreach ($allRatings as $r) {
            $sum += (int) $r->getRating();
        }
        $avg = $sum / \count($allRatings);

        // Save average to Mods entity
        $mod->setRating($avg);
        $em->flush();

        // Build breakdown for 1–10
        $stats = $ratingRepo->getRatingStats($mod);

        return $this->json([
            'success' => true,
            'rating' => $avg,
            'count' => \count($allRatings),
            'stats' => $stats,
        ]);
    }
}
