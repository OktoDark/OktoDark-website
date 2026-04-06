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

use App\Entity\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/settings')]
class SettingsController extends AbstractController
{
    #[Route('', name: 'admin_settings')]
    public function settings(Request $request, EntityManagerInterface $em): Response
    {
        // Load the single settings row
        $settings = $em->getRepository(Settings::class)->findOneBy([]) ?? new Settings();

        // Handle form submit
        if ($request->isMethod('POST')) {
            $settings->setSiteName($request->request->get('siteName'));
            $settings->setJobmail($request->request->get('jobmail'));
            $settings->setSiteCDN($request->request->get('siteCDN'));
            $settings->setLogoName($request->request->get('logoName'));

            $em->persist($settings);
            $em->flush();

            $this->addFlash('success', 'Settings saved successfully.');
        }

        return $this->render('@theme/admin/settings.html.twig', [
            'settings' => $settings,
        ]);
    }
}
