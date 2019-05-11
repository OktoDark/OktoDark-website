<?php
/**
 * Copyright © 2019 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 16.03.2019 17:30
 */

namespace App\Controller;

use App\Form\ContactType;
use App\Repository\SettingsRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InfoController extends AbstractController
{
    /**
     * @Route("/about", methods={"GET"}, name="about")
     *
     * @return Response
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
    public function contact(SettingsRepository $settings, Request $request, \Swift_Mailer $mailer): Response
    {
        $selectSettings = $settings->findAll();

        $form = $this->createForm(ContactType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $contactFormData = $form->getData();

            $message = (new \Swift_Message($contactFormData['subject']))
                ->setFrom($contactFormData['email'])
                ->setTo('contact@oktodark.com')
                ->setBody(
                    $contactFormData['message'],
                    'text/plain'
                )
                ;

            $mailer->send($message);

            $this->addFlash('success', 'Message Send!');

            return $this->redirectToRoute('contact');
        }

        return $this->render('@theme/info/contact.html.twig', [
            'settings' => $selectSettings,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/faq", methods={"GET"}, name="faq")
     *
     * @return Response
     */
    public function faq(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/info/faq.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/privacy-policy", methods={"GET"}, name="privacy-policy")
     *
     * @return Response
     */
    public function privacypolicy(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/info/privacy-policy.html.twig', ['settings' => $selectSettings]);
    }

    /**
     * @Route("/services", methods={"GET"}, name="services")
     *
     * @return Response
     */
    public function services(SettingsRepository $settings): Response
    {
        $selectSettings = $settings->findAll();

        return $this->render('@theme/info/services.html.twig', ['settings' => $selectSettings]);
    }
}