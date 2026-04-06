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

use App\Entity\Careers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/careers')]
class CareersController extends AbstractController
{
    #[Route('', name: 'admin_careers')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Careers::class);

        // CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $career = $id ? $repo->find($id) : new Careers();

            $career->setJobTitle($request->request->get('jobTitle'));
            $career->setRequirements($request->request->get('requirements'));
            $career->setNumber($request->request->get('number'));

            $em->persist($career);
            $em->flush();

            $this->addFlash('success', $id ? 'Career position updated.' : 'Career position created.');
            return $this->redirectToRoute('admin_careers');
        }

        // DELETE
        if ($request->query->get('delete')) {
            $career = $repo->find($request->query->get('delete'));
            if ($career) {
                $em->remove($career);
                $em->flush();
                $this->addFlash('success', 'Career position deleted.');
            }
            return $this->redirectToRoute('admin_careers');
        }

        return $this->render('@theme/admin/careers.html.twig', [
            'careers' => $repo->findAll(),
        ]);
    }
}