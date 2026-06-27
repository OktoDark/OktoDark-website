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

use App\Entity\Board;
use App\Entity\BoardColumn;
use App\Entity\OurGames;
use App\Entity\User;
use App\Form\OurGamesType;
use App\Repository\OurGamesRepository;
use App\Security\Attribute\Permission;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin/our-games')]
#[Permission('admin.our_games.index', group: 'Admin', label: 'View Our Games')]
class AdminOurGamesController extends AbstractController
{
    private $slugger;
    private $parameterBag;
    private $filesystem;
    private $validator;
    private const BASE_UPLOAD_DIR = '/public/uploads/games';

    public function __construct(SluggerInterface $slugger, ParameterBagInterface $parameterBag, Filesystem $filesystem, ValidatorInterface $validator)
    {
        $this->slugger = $slugger;
        $this->parameterBag = $parameterBag;
        $this->filesystem = $filesystem;
        $this->validator = $validator;
    }

    #[Route('/', name: 'admin_our_games_index', methods: ['GET'])]
    public function index(OurGamesRepository $ourGamesRepository): Response
    {
        return $this->render('@theme/admin/our_games/index.html.twig', [
            'our_games' => $ourGamesRepository->findAll(),
        ]);
    }

    private function getFormErrors(Form $form): array
    {
        $errors = ['_global' => [], 'fields' => []];
        foreach ($form->getErrors(true, true) as $error) {
            $propertyPath = $error->getCause() ? $error->getCause()->getPropertyPath() : null;
            $message = $error->getMessage();

            if ($propertyPath) {
                $errors['fields'][$propertyPath][] = $message;
            } else {
                $errors['_global'][] = $message;
            }
        }

        return $errors;
    }

    #[Route('/new', name: 'admin_our_games_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $ourGame = new OurGames();
        $form = $this->createForm(OurGamesType::class, $ourGame, [
            'action' => $this->generateUrl('admin_our_games_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Get the name from the form data immediately after handling the request
            $name = $form->get('Name')->getData();
            if ($name) {
                $safeShortName = $this->slugger->slug($name);
                $ourGame->setShortNameSlug($safeShortName->lower());
            }

            if ($form->isValid()) {
                $gameBaseDir = $this->parameterBag->get('kernel.project_dir').self::BASE_UPLOAD_DIR.'/'.$ourGame->getShortNameSlug(); // Use the slug from the entity
                $coverDir = $gameBaseDir.'/covers';
                $imagesDir = $gameBaseDir.'/images';
                $videosDir = $gameBaseDir.'/videos';

                // Ensure directories exist
                $this->filesystem->mkdir($coverDir);
                $this->filesystem->mkdir($imagesDir);
                $this->filesystem->mkdir($videosDir);

                // Handle Cover File Upload
                /** @var UploadedFile $coverFile */
                $coverFile = $form->get('CoverFile')->getData();
                if ($coverFile) {
                    $newFilename = $ourGame->getShortNameSlug().'-cover.'.$coverFile->guessExtension();
                    try {
                        $coverFile->move($coverDir, $newFilename);
                    } catch (FileException $e) {
                        return new JsonResponse(['success' => false, 'message' => 'Error uploading cover file: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    $ourGame->setCover($newFilename);
                }

                // Handle Multiple Image Files Upload
                /** @var UploadedFile[] $imageFiles */
                $imageFiles = $form->get('ImagesFiles')->getData();
                $currentImages = $ourGame->getImages();
                foreach ($imageFiles as $imageFile) {
                    $newFilename = $ourGame->getShortNameSlug().'-img-'.uniqid().'.'.$imageFile->guessExtension();
                    try {
                        $imageFile->move($imagesDir, $newFilename);
                        $currentImages[] = $newFilename;
                    } catch (FileException $e) {
                        return new JsonResponse(['success' => false, 'message' => 'Error uploading image file: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
                $ourGame->setImages($currentImages);

                // Mark the entity as changed so Doctrine detects the JSON field update
                $entityManager->persist($ourGame);

                // Handle Multiple Video Files Upload
                /** @var UploadedFile[] $videoFiles */
                $videoFiles = $form->get('VideosFiles')->getData();
                $currentVideos = $ourGame->getVideos();
                foreach ($videoFiles as $videoFile) {
                    $newFilename = $ourGame->getShortNameSlug().'-vid-'.uniqid().'.'.$videoFile->guessExtension();
                    try {
                        $videoFile->move($videosDir, $newFilename);
                        $currentVideos[] = $newFilename;
                    } catch (FileException $e) {
                        return new JsonResponse(['success' => false, 'message' => 'Error uploading video file: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
                $ourGame->setVideos($currentVideos);

                // Ensure all changes are properly marked for persistence
                $entityManager->persist($ourGame);
                $entityManager->getUnitOfWork()->computeChangeSets();
                $entityManager->flush();

                // --- START: Bug-tracker Board Creation Logic ---
                /** @var User $currentUser */
                $currentUser = $this->getUser();
                if (!$currentUser) {
                    return new JsonResponse(['success' => false, 'message' => 'User not authenticated for board creation.'], Response::HTTP_UNAUTHORIZED);
                }

                $bugBoard = new Board();
                $bugBoard->setTitle('Bug Tracker for '.$ourGame->getName());
                $bugBoard->setOwner($currentUser);
                $bugBoard->setOurGame($ourGame);
                $bugBoard->setIsPublic(false);

                $boardErrors = $this->validator->validate($bugBoard);
                if (\count($boardErrors) > 0) {
                    $errorMessages = [];
                    foreach ($boardErrors as $error) {
                        $errorMessages[] = $error->getMessage().' (Property: '.$error->getPropertyPath().')';
                    }

                    return new JsonResponse(['success' => false, 'message' => 'Failed to create bug tracker board: '.implode(', ', $errorMessages)], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                $entityManager->persist($bugBoard);
                $entityManager->flush(); // Flush to get an ID for the board

                // Create a default column for the new board
                $defaultColumn = new BoardColumn();
                $defaultColumn->setTitle('To Do');
                $defaultColumn->setPosition(0);
                $defaultColumn->setBoard($bugBoard);

                $columnErrors = $this->validator->validate($defaultColumn);
                if (\count($columnErrors) > 0) {
                    $errorMessages = [];
                    foreach ($columnErrors as $error) {
                        $errorMessages[] = $error->getMessage().' (Property: '.$error->getPropertyPath().')';
                    }

                    return new JsonResponse(['success' => false, 'message' => 'Failed to create default column for bug tracker: '.implode(', ', $errorMessages)], Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                $entityManager->persist($defaultColumn);

                // Link the bugBoard back to OurGame's bugLink property
                $ourGame->setBugLink($bugBoard);
                $entityManager->persist($ourGame); // Persist OurGame again to save the bugLink

                $entityManager->flush(); // Final flush for column and updated OurGame

                // --- END: Bug-tracker Board Creation Logic ---

                return new JsonResponse(['success' => true, 'message' => 'Game added successfully with bug tracker!']);
            }
            // Form is submitted but invalid, return errors as JSON
            if ($request->isXmlHttpRequest()) {
                $errors = $this->getFormErrors($form);

                return new JsonResponse(['success' => false, 'message' => 'Form validation failed.', 'errors' => $errors], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('@theme/admin/our_games/_form.html.twig', [
                'form' => $form->createView(),
                'our_game' => $ourGame,
            ]);
        }

        return $this->redirectToRoute('admin_our_games_index');
    }

    #[Route('/{id}/edit', name: 'admin_our_games_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, OurGames $ourGame, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(OurGamesType::class, $ourGame, [
            'action' => $this->generateUrl('admin_our_games_edit', ['id' => $ourGame->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Get the name from the form data immediately after handling the request
            $name = $form->get('Name')->getData();
            if ($name) {
                $safeShortName = $this->slugger->slug($name);
                $ourGame->setShortNameSlug($safeShortName->lower());
            }

            if ($form->isValid()) {
                $gameBaseDir = $this->parameterBag->get('kernel.project_dir').self::BASE_UPLOAD_DIR.'/'.$ourGame->getShortNameSlug();
                $coverDir = $gameBaseDir.'/covers';
                $imagesDir = $gameBaseDir.'/images';
                $videosDir = $gameBaseDir.'/videos';

                // Ensure directories exist
                $this->filesystem->mkdir($coverDir);
                $this->filesystem->mkdir($imagesDir);
                $this->filesystem->mkdir($videosDir);

                // Handle Cover File Upload
                /** @var UploadedFile $coverFile */
                $coverFile = $form->get('CoverFile')->getData();
                if ($coverFile) {
                    // Delete old cover if it exists
                    if ($ourGame->getCover()) {
                        $oldFilePath = $coverDir.'/'.$ourGame->getCover();
                        if ($this->filesystem->exists($oldFilePath)) {
                            $this->filesystem->remove($oldFilePath);
                        }
                    }
                    $newFilename = $ourGame->getShortNameSlug().'-cover.'.$coverFile->guessExtension();
                    try {
                        $coverFile->move($coverDir, $newFilename);
                    } catch (FileException $e) {
                        return new JsonResponse(['success' => false, 'message' => 'Error uploading cover file: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                    $ourGame->setCover($newFilename);
                }

                // Handle Multiple Image Files Upload
                /** @var UploadedFile[] $imageFiles */
                $imageFiles = $form->get('ImagesFiles')->getData();
                $currentImages = $ourGame->getImages();
                foreach ($imageFiles as $imageFile) {
                    $newFilename = $ourGame->getShortNameSlug().'-img-'.uniqid().'.'.$imageFile->guessExtension();
                    try {
                        $imageFile->move($imagesDir, $newFilename);
                        $currentImages[] = $newFilename;
                    } catch (FileException $e) {
                        return new JsonResponse(['success' => false, 'message' => 'Error uploading image file: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
                $ourGame->setImages($currentImages);

                // Mark the entity as changed so Doctrine detects the JSON field update
                $entityManager->persist($ourGame);

                // Handle Multiple Video Files Upload
                /** @var UploadedFile[] $videoFiles */
                $videoFiles = $form->get('VideosFiles')->getData();
                $currentVideos = $ourGame->getVideos();
                foreach ($videoFiles as $videoFile) {
                    $newFilename = $ourGame->getShortNameSlug().'-vid-'.uniqid().'.'.$videoFile->guessExtension();
                    try {
                        $videoFile->move($videosDir, $newFilename);
                        $currentVideos[] = $newFilename;
                    } catch (FileException $e) {
                        return new JsonResponse(['success' => false, 'message' => 'Error uploading video file: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
                $ourGame->setVideos($currentVideos);

                // Ensure all changes are tracked
                $entityManager->persist($ourGame);
                $entityManager->getUnitOfWork()->computeChangeSets();
                $entityManager->flush();

                return new JsonResponse(['success' => true, 'message' => 'Game updated successfully!']);
            }
            // Form is submitted but invalid, return errors as JSON
            if ($request->isXmlHttpRequest()) {
                $errors = $this->getFormErrors($form);

                return new JsonResponse(['success' => false, 'message' => 'Form validation failed.', 'errors' => $errors], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('@theme/admin/our_games/_form.html.twig', [
                'form' => $form->createView(),
                'our_game' => $ourGame,
            ]);
        }

        return $this->redirectToRoute('admin_our_games_index');
    }

    #[Route('/{id}', name: 'admin_our_games_delete', methods: ['POST'])]
    public function delete(Request $request, OurGames $ourGame, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ourGame->getId(), $request->request->get('_token'))) {
            $safeShortName = $this->slugger->slug($ourGame->getName()); // Use getName() instead of getShortName()
            $gameBaseDir = $this->parameterBag->get('kernel.project_dir').self::BASE_UPLOAD_DIR.'/'.$safeShortName;

            // Remove the entire game directory
            if ($this->filesystem->exists($gameBaseDir)) {
                $this->filesystem->remove($gameBaseDir);
            }

            $entityManager->remove($ourGame);
            $entityManager->flush();
            $this->addFlash('success', 'Game deleted successfully!');
        }

        return $this->redirectToRoute('admin_our_games_index', [], Response::HTTP_SEE_OTHER);
    }
}
