<?php

namespace App\Controller;

use App\Repository\SettingsRepository;
use App\Form\Type\ChangePasswordType;
use App\Form\UserType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Controller used to manage current user.
 *
 * @Route("/profile")
 * @Security("has_role('ROLE_MEMBER')")
 *
 * @author Romain Monteil <monteil.romain@gmail.com>
 */
class UserController extends AbstractController
{
    /**
     * @Route("/edit", methods={"GET", "POST"}, name="user_edit")
     */
    public function edit(SettingsRepository $settings, Request $request): Response
    {
        $selectSettings = $settings->findAll();

        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            $this->addFlash('success', 'user.updated_successfully');

            return $this->redirectToRoute('user_edit');
        }

        return $this->render('@theme/member/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'settings' => $selectSettings,
        ]);
    }

    /**
     * @Route("/change-password", methods={"GET", "POST"}, name="user_change_password")
     */
    public function changePassword(SettingsRepository $settings, Request $request, UserPasswordEncoderInterface $encoder): Response
    {
        $selectSettings = $settings->findAll();

        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($encoder->encodePassword($user, $form->get('newPassword')->getData()));

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('security_logout');
        }

        return $this->render('@theme/member/change_password.html.twig', [
            'form' => $form->createView(),
            'settings' => $selectSettings,
        ]);
    }
}
