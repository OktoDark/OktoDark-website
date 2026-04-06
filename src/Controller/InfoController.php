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
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(TeamRepository $team): Response
    {
        return $this->render('@theme/info/about.html.twig', [
            'team' => $team->findAll(),
        ]);
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(Request $request, MailerInterface $mailer): Response
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
            'form' => $form,
        ]);
    }

    #[Route('/faq', name: 'faq', methods: ['GET'])]
    public function faq(): Response
    {
        return $this->render('@theme/info/faq.html.twig', [
        ]);
    }

    #[Route('/privacy-policy', name: 'privacy-policy', methods: ['GET'])]
    public function privacypolicy(): Response
    {
        return $this->render('@theme/info/privacy-policy.html.twig', [
        ]);
    }

    /**
     * @Route("/services", methods="GET", name="services")
     */
    #[Route('/services', name: 'services', methods: ['GET'])]
    public function services(ServicesRepository $services): Response
    {
        return $this->render('@theme/info/services.html.twig', [
            'services' => $services->findAll(),
        ]);
    }
}
