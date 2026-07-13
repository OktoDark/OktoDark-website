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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    /**
     * Displays and processes the contact form submission.
     *
     * Applies layered bot protection including session-bound captcha generation,
     * User-Agent and Referer checks, honeypot validation, timestamp/checksum
     * validation, rate limiting, and math captcha verification before persisting
     * the submitted contact message.
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param RateLimiterFactory $contactFormLimiter
     * @return Response
     */
    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        EntityManagerInterface $em,
        RateLimiterFactory $contactFormLimiter,
    ): Response {
        $session = $request->getSession();

        // 1. Block POST without session
        if ($request->isMethod('POST') && !$session->has('captcha_result')) {
            return new Response('Invalid session.', 400);
        }

        // 2. Block bots with missing headers
        if ($request->isMethod('POST')) {
            $ua = $request->headers->get('User-Agent');
            $ref = $request->headers->get('Referer');

            if (!$ua || mb_strlen($ua) < 10) {
                return new Response('Bot UA blocked', 400);
            }

            if (!$ref || !str_contains($ref, $request->getHost())) {
                return new Response('Invalid referer', 400);
            }
        }

        // 3. Generate captcha
        if ($request->isMethod('GET') || !$session->has('captcha_result')) {
            $num1 = random_int(1, 9);
            $num2 = random_int(1, 9);
            $session->set('captcha_result', $num1 + $num2);
            $session->set('captcha_question', \sprintf('%d + %d = ?', $num1, $num2));
        }

        // 4. Create form
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // 5. Honeypot
            if (!empty($form->get('website')->getData())) {
                return new Response('OK', 200);
            }

            // 6. Timestamp validation
            $timestamp = (int) $form->get('form_timestamp')->getData();
            if (!$timestamp || (time() - $timestamp) < 3) {
                return new Response('Too fast', 400);
            }

            // 7. Checksum validation
            $checksum = $form->get('form_checksum')->getData();
            $expected = hash('sha256', $timestamp.$_ENV['APP_SECRET']);
            if ($checksum !== $expected) {
                return new Response('Invalid checksum', 400);
            }

            // 8. Rate limiting
            $limiter = $contactFormLimiter->create($request->getClientIp());
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Too many requests. Try again later.');

                return $this->render('@theme/contact.html.twig', [
                    'form' => $form,
                    'captcha_question' => $session->get('captcha_question'),
                ], new Response(null, Response::HTTP_TOO_MANY_REQUESTS));
            }

            // 9. Captcha validation
            $userAnswer = $form->get('captcha_answer')->getData();
            $correctAnswer = $session->get('captcha_result');

            if ($userAnswer !== $correctAnswer) {
                $form->get('captcha_answer')->addError(
                    new FormError('Incorrect answer. Please solve the math problem.')
                );
            }

            // 10. Final validation
            if ($form->isValid()) {
                $contact = $form->getData();
                $em->persist($contact);
                $em->flush();

                $session->remove('captcha_result');
                $session->remove('captcha_question');

                $this->addFlash('success', 'Message Sent!');

                return $this->redirectToRoute('contact');
            }
        }

        return $this->render('@theme/contact.html.twig', [
            'form' => $form,
            'captcha_question' => $session->get('captcha_question'),
        ]);
    }
}
