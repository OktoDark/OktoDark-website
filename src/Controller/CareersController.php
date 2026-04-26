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

use App\Entity\CareerApplication;
use App\Repository\CareersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class CareersController extends AbstractController
{
    #[Route('/careers', name: 'careers', methods: ['GET', 'POST'])]
    public function careers(
        Request $request,
        CareersRepository $careersRepository,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $jobTitle = $request->request->get('job_title');
            $messageContent = $request->request->get('message');
            $token = $request->request->get('_token');

            /** @var UploadedFile $cvFile */
            $cvFile = $request->files->get('cv');

            if (!$this->isCsrfTokenValid('career_apply', $token)) {
                $this->addFlash('error', 'Invalid security token.');

                return $this->redirectToRoute('careers');
            }

            if ($name && $email && $jobTitle && $messageContent && $cvFile) {
                $application = new CareerApplication();
                $application->setName($name);
                $application->setEmail($email);
                $application->setJobTitle($jobTitle);
                $application->setMessage($messageContent);

                // Handle CV Upload
                $originalFilename = pathinfo($cvFile->getClientOriginalName(), \PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

                try {
                    $cvFile->move(
                        $this->getParameter('cv_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading CV. Please try again.');

                    return $this->redirectToRoute('careers');
                }

                $application->setCvFilename($newFilename);

                $em->persist($application);
                $em->flush();

                $this->addFlash('success', 'Your application has been sent successfully!');

                return $this->redirectToRoute('careers');
            }

            $this->addFlash('error', 'Please fill all required fields and upload your CV.');
        }

        // Sort by publishedAt DESC
        return $this->render('@theme/careers.html.twig', [
            'careers' => $careersRepository->findBy([], ['publishedAt' => 'DESC']),
        ]);
    }
}
