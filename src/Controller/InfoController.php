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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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

    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, EntityManagerInterface $em, RateLimiterFactory $contactFormLimiter): Response
    {
        $session = $request->getSession();

        // Generate a new math challenge if it doesn't exist or on every GET request
        if ($request->isMethod('GET') || !$session->has('captcha_result')) {
            $num1 = random_int(1, 9);
            $num2 = random_int(1, 9);
            $session->set('captcha_result', $num1 + $num2);
            $session->set('captcha_question', \sprintf('%d + %d = ?', $num1, $num2));
        }

        $form = $this->createForm(ContactType::class, null, [
            // No custom options needed here for now
        ]);

        // Dynamically set the label for the captcha field
        $form->get('captcha_answer')->getConfig()->getOption('label'); // Dummy call to ensure config is loaded
        // Note: Changing options after creation is tricky in Symfony,
        // so we'll just pass the question to the template instead.

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // 1. Rate Limiting Check
            $limiter = $contactFormLimiter->create($request->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Too many requests. Please try again later.');

                return $this->render('@theme/info/contact.html.twig', [
                    'form' => $form,
                    'captcha_question' => $session->get('captcha_question'),
                ], new Response(null, Response::HTTP_TOO_MANY_REQUESTS));
            }

            // 2. Math Challenge Verification
            $userAnswer = $form->get('captcha_answer')->getData();
            $correctAnswer = $session->get('captcha_result');

            if ($userAnswer !== $correctAnswer) {
                $form->get('captcha_answer')->addError(new FormError('Incorrect answer. Please solve the math problem correctly.'));
            }

            if ($form->isValid()) {
                $contact = $form->getData();
                $em->persist($contact);
                $em->flush();

                // Clear captcha after success
                $session->remove('captcha_result');
                $session->remove('captcha_question');

                $this->addFlash('success', 'Message Sent!');

                return $this->redirectToRoute('contact');
            }
        }

        return $this->render('@theme/info/contact.html.twig', [
            'form' => $form,
            'captcha_question' => $session->get('captcha_question'),
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
