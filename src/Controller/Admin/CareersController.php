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

use App\Entity\CareerApplication;
use App\Entity\Careers;
use App\Repository\CareerApplicationRepository;
use App\Security\Attribute\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/careers')]
#[Permission('admin.careers.index', group: 'Admin', label: 'View Careers')]
class CareersController extends AbstractController
{
    /**
     * Initialize controller dependencies for persistence and application queries.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private CareerApplicationRepository $applicationRepo,
    ) {
    }

    /**
     * List career positions and handle their create/update/delete via the same route.
     *
     * POST persists a new or existing position (requirements are filtered to
     * non-empty strings); a "delete" query parameter removes a position. Otherwise
     * the full list is rendered.
     */
    #[Route('', name: 'admin_careers')]
    public function index(Request $request): Response
    {
        $repo = $this->em->getRepository(Careers::class);

        // CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $career = $id ? $repo->find($id) : new Careers();

            $career->setJobTitle($request->request->get('jobTitle'));
            $career->setDescription($request->request->get('description'));
            $career->setNumber($request->request->get('number'));

            if ($request->request->get('publishedAt')) {
                $career->setPublishedAt(new \DateTime($request->request->get('publishedAt')));
            }

            // Get requirements as array
            $requirements = $request->request->all('requirements');
            if (!\is_array($requirements)) {
                $requirements = [];
            }
            // Filter empty values
            $requirements = array_values(array_filter($requirements, static fn ($v) => !empty(mb_trim($v))));

            $career->setRequirements($requirements);

            $this->em->persist($career);
            $this->em->flush();

            $this->addFlash('success', $id ? 'Career position updated.' : 'Career position created.');

            return $this->redirectToRoute('admin_careers');
        }

        // DELETE
        if ($request->query->get('delete')) {
            $career = $repo->find($request->query->get('delete'));
            if ($career) {
                $this->em->remove($career);
                $this->em->flush();
                $this->addFlash('success', 'Career position deleted.');
            }

            return $this->redirectToRoute('admin_careers');
        }

        return $this->render('@theme/admin/careers.html.twig', [
            'careers' => $repo->findAll(),
        ]);
    }

    /**
     * List all career applications ordered by most recent submission.
     */
    #[Route('/applications', name: 'admin_careers_applications_index')]
    public function applications(): Response
    {
        return $this->render('@theme/admin/careers/index.html.twig', [
            'applications' => $this->applicationRepo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    /**
     * Display a single career application's details.
     */
    #[Route('/applications/{id}', name: 'admin_careers_applications_show')]
    public function showApplication(CareerApplication $application): Response
    {
        return $this->render('@theme/admin/careers/show.html.twig', [
            'application' => $application,
        ]);
    }

    /**
     * Update the status of a career application and redirect back to its detail page.
     */
    #[Route('/applications/{id}/status', name: 'admin_careers_applications_status', methods: ['POST'])]
    public function updateApplicationStatus(CareerApplication $application, Request $request): Response
    {
        $status = $request->request->get('status');
        $application->setStatus($status);
        $this->em->flush();

        $this->addFlash('success', 'Application status updated.');

        return $this->redirectToRoute('admin_careers_applications_show', ['id' => $application->getId()]);
    }

    /**
     * Delete a career application after CSRF token validation and redirect to the list.
     */
    #[Route('/applications/{id}/delete', name: 'admin_careers_applications_delete', methods: ['POST'])]
    public function deleteApplication(CareerApplication $application, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete'.$application->getId(), $request->request->get('_token'))) {
            $this->em->remove($application);
            $this->em->flush();
            $this->addFlash('success', 'Application deleted.');
        }

        return $this->redirectToRoute('admin_careers_applications_index');
    }
}
