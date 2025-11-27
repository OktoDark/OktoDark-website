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

use App\Form\ContactType;
use App\Repository\ServicesRepository;
use App\Repository\SettingsRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    #[Route('/about', methods: ['GET'], name: 'about')]
    public function about(SettingsRepository $settings, TeamRepository $team): Response
    {
        return $this->render('@theme/info/about.html.twig', [
            'settings' => $settings->findAll(),
            'team' => $team->findAll(),
        ]);
    }

    #[Route('/contact', methods: ['GET'], name: 'contact')]
    public function contact(SettingsRepository $settings, Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactFormData = $form->getData();

            $email = (new Email($contactFormData['subject']))
                ->from($contactFormData['email'])
                ->to('contact@oktodark.com')
                ->html($contactFormData['message'])
            ;

            $mailer->send($email);

            $this->addFlash('success', 'Message Send!');

            return $this->redirectToRoute('contact');
        }

        return $this->render('@theme/info/contact.html.twig', [
            'settings' => $settings->findAll(),
            'form' => $form,
        ]);
    }

    #[Route('/faq', methods: ['GET'], name: 'faq')]
    public function faq(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/info/faq.html.twig', ['settings' => $selectSettings]);
    }

    #[Route('/privacy-policy', methods: ['GET'], name: 'privacy-policy')]
    public function privacypolicy(SettingsRepository $settings): Response
    {
        return $this->render('@theme/info/privacy-policy.html.twig', [
            'settings' => $settings->findAll(),
        ]);
    }

    /**
     * @Route("/services", methods="GET", name="services")
     */
    #[Route('/services', methods: ['GET'], name: 'services')]
    public function services(SettingsRepository $settings, ServicesRepository $services): Response
    {
        return $this->render('@theme/info/services.html.twig', [
            'settings' => $settings->findAll(),
            'services' => $services->findAll(),
        ]);
    }
}
