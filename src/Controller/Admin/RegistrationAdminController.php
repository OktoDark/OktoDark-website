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

use App\Repository\RegistrationWaitlistRepository;
use App\Service\SettingsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class RegistrationAdminController extends AbstractController
{
    public function __construct(
        private SettingsProvider $settingsProvider,
        private RegistrationWaitlistRepository $waitlistRepo,
    ) {
    }

    #[Route('/admin/registration', name: 'admin_registration_panel')]
    public function index(): Response
    {
        return $this->render('@theme/admin/registration_panel.html.twig', [
            'registration_enabled' => $this->settingsProvider->isRegistrationEnabled(),
            'waitlist_count' => $this->waitlistRepo->count([]),
        ]);
    }

    #[Route('/admin/registration/toggle', name: 'admin_registration_toggle', methods: ['POST'])]
    public function toggle(): RedirectResponse
    {
        $enabled = !$this->settingsProvider->isRegistrationEnabled();
        $this->settingsProvider->setRegistrationEnabled($enabled);

        $this->addFlash('success', $enabled ? 'Registration enabled.' : 'Registration disabled.');

        return $this->redirectToRoute('admin_registration_panel');
    }
}
