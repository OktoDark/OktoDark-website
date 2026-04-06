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

use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/team')]
class TeamController extends AbstractController
{
    #[Route('', name: 'admin_team')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $teamRepo = $em->getRepository(Team::class);

        // Handle CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $member = $id ? $teamRepo->find($id) : new Team();

            $member->setPosition($request->request->get('position'));
            $member->setName($request->request->get('name'));
            $member->setDescription($request->request->get('description'));

            // Handle image upload
            $file = $request->files->get('teamImage');
            if ($file) {
                $filename = uniqid('team_').'.'.$file->guessExtension();
                $file->move('uploads/team', $filename);
                $member->setTeamImage($filename);
            }

            $em->persist($member);
            $em->flush();

            $this->addFlash('success', $id ? 'Team member updated.' : 'Team member added.');

            return $this->redirectToRoute('admin_team');
        }

        // Handle DELETE
        if ($request->query->get('delete')) {
            $member = $teamRepo->find($request->query->get('delete'));
            if ($member) {
                $em->remove($member);
                $em->flush();
                $this->addFlash('success', 'Team member deleted.');
            }

            return $this->redirectToRoute('admin_team');
        }

        return $this->render('@theme/admin/team.html.twig', [
            'team' => $teamRepo->findAll(),
        ]);
    }
}
