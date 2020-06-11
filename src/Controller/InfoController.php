<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 13.01.2020, 06:50
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
use Symfony\Component\Routing\Annotation\Route;

class InfoController extends AbstractController
{
    /**
     * @Route("/about", methods="GET", name="about")
     */
    public function about(SettingsRepository $settings, TeamRepository $team): Response
    {
        $selectSettings = $settings->findAll();
        $viewTeam = $team->findAll();

        return $this->render('@theme/info/about.html.twig', [
            'settings' => $selectSettings,
            'team' => $viewTeam,
        ]);
    }

    /**
     * @Route("/contact", name="contact")
     */
    public function contact(SettingsRepository $settings, Request $request, MailerInterface $mailer): Response
    {
        $selectSettings = $settings->findAll();

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
            'settings' => $selectSettings,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/faq", methods="GET", name="faq")
     */
    public function faq(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/info/faq.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/privacy-policy", methods="GET", name="privacy-policy")
     */
    public function privacypolicy(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/info/privacy-policy.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/services", methods="GET", name="services")
     */
    public function services(SettingsRepository $settings, ServicesRepository $services): Response
    {
        $selectSettings = $settings->findAll();
        $showServices = $services->findAll();

        return $this->render('@theme/info/services.html.twig', [
            'settings' => $selectSettings,
            'services' => $showServices,
        ]);
    }
}