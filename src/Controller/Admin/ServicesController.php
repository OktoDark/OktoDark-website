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

use App\Entity\Services;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/services')]
class ServicesController extends AbstractController
{
    #[Route('', name: 'admin_services')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Services::class);

        // CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $service = $id ? $repo->find($id) : new Services();

            $service->setServiceName($request->request->get('serviceName'));
            $service->setServicePrice($request->request->get('servicePrice'));
            $service->setServiceWorkDays($request->request->get('serviceWorkDays'));
            $service->setServiceToDo($request->request->get('serviceToDo'));

            $em->persist($service);
            $em->flush();

            $this->addFlash('success', $id ? 'Service updated.' : 'Service created.');
            return $this->redirectToRoute('admin_services');
        }

        // DELETE
        if ($request->query->get('delete')) {
            $service = $repo->find($request->query->get('delete'));
            if ($service) {
                $em->remove($service);
                $em->flush();
                $this->addFlash('success', 'Service deleted.');
            }
            return $this->redirectToRoute('admin_services');
        }

        return $this->render('@theme/admin/services.html.twig', [
            'services' => $repo->findAll(),
        ]);
    }
}