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

use App\Entity\User;
use App\Form\Type\RegistrationFormType;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="security_login")
     */
    public function login(AuthenticationUtils $authenticationUtils, SettingsRepository $settings): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('member_area');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $selectSettings = $settings->findAll();

        return $this->render('@theme/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'settings' => $selectSettings,
        ]);
    }

    /**
     * @Route("/logout", name="security_logout")
     */
    public function logout()
    {
        throw new \LogicException('This should never be reached!');
    }

    /**
     * @Route("/register", name="register_index")
     */
    public function register(SettingsRepository $settings, Request $request, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        $selectSettings = $settings->findAll();

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $passwordEncoder->encodePassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            // be absolutely sure they agree
            if (true === $form['agreeTerms']->getData()) {
                $user->agreeToTerms();
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            // do anything else you need here, like send an email
            // in this example, we are just redirecting to the homepage
            return $this->redirectToRoute('home_index');
        }

        return $this->render('@theme/member/register.html.twig', [
            'settings' => $selectSettings,
            'registrationForm' => $form->createView(),
        ]);
    }
}
