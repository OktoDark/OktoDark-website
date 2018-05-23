<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 09.05.2018 12:20
 */

namespace App\Controller;

use App\Repository\SettingsRepository;
use App\Repository\TeamRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InfoController extends Controller
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

        return $this->render('@theme/info/about.html.twig', ['settings' => $selectSettings, 'team' => $viewTeam]);
    }

    /**
     * @Route("/contact", methods={"GET"}, name="contact")
     *
     * @return Response
     */
    public function contact(SettingsRepository $settings, Request $request, \Swift_Mailer $mailer): Response
    {
        $selectSettings = $settings->findAll();

        $contact = ['message' => 'Type your message'];

        $form = $this->createFormBuilder($contact)
            ->add('name', TextType::class)
            ->add('email', EmailType::class)
            ->add('subject', TextType::class)
            ->add('message', TextareaType::class)
            ->add('submit', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $message = (new \Swift_Mailer('.$name.'))
                ->setFrom('..')
                ->setTo('')
                ->setBody('')
                ;

            $mailer->send($message);
            $contact = $form->getData();
        }

        return $this->render('@theme/info/contact.html.twig', ['settings' => $selectSettings, 'form' => $form->createView()]);
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