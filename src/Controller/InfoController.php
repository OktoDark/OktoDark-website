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

use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    /**
     * Displays the about page with team information.
     */
    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(TeamRepository $team): Response
    {
        return $this->render('@theme/info/about.html.twig', [
            'team' => $team->findAll(),
        ]);
    }

    /**
     * Displays the frequently asked questions page.
     */
    #[Route('/faq', name: 'faq', methods: ['GET'])]
    public function faq(): Response
    {
        return $this->render('@theme/info/faq.html.twig', [
        ]);
    }

    /**
     * Displays the privacy policy page.
     */
    #[Route('/privacy-policy', name: 'privacy-policy', methods: ['GET'])]
    public function privacypolicy(): Response
    {
        return $this->render('@theme/info/privacy-policy.html.twig', [
        ]);
    }

    /**
     * Displays the terms and conditions page.
     */
    #[Route('/terms-and-conditions', name: 'terms-and-conditions', methods: ['GET'])]
    public function termsAndConditions(): Response
    {
        return $this->render('@theme/info/terms-and-conditions.html.twig', [
        ]);
    }
}
