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

use App\Entity\Assets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/assets')]
class AssetsController extends AbstractController
{
    #[Route('', name: 'admin_assets')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Assets::class);

        // CREATE or UPDATE
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $asset = $id ? $repo->find($id) : new Assets();

            $asset->setName($request->request->get('name'));
            $asset->setSku((int) $request->request->get('sku'));
            $asset->setDescription($request->request->get('description'));
            $asset->setWebsite($request->request->get('website'));

            // JSON fields (stored as arrays)
            $asset->setBrand(json_decode($request->request->get('brand') ?: '[]', true));
            $asset->setHairtype(json_decode($request->request->get('hairtype') ?: '[]', true));
            $asset->setClothing(json_decode($request->request->get('clothing') ?: '[]', true));
            $asset->setMisc(json_decode($request->request->get('misc') ?: '[]', true));
            $asset->setRequirement(json_decode($request->request->get('requirement') ?: '[]', true));
            $asset->setSoftwarecompatible(json_decode($request->request->get('softwarecompatible') ?: '[]', true));
            $asset->setFigurecompatible(json_decode($request->request->get('figurecompatible') ?: '[]', true));
            $asset->setGenre(json_decode($request->request->get('genre') ?: '[]', true));

            $asset->setBundle('1' === $request->request->get('bundle'));

            $em->persist($asset);
            $em->flush();

            $this->addFlash('success', $id ? 'Asset updated.' : 'Asset created.');

            return $this->redirectToRoute('admin_assets');
        }

        // DELETE
        if ($request->query->get('delete')) {
            $asset = $repo->find($request->query->get('delete'));
            if ($asset) {
                $em->remove($asset);
                $em->flush();
                $this->addFlash('success', 'Asset deleted.');
            }

            return $this->redirectToRoute('admin_assets');
        }

        return $this->render('@theme/admin/assets.html.twig', [
            'assets' => $repo->findAll(),
        ]);
    }
}
