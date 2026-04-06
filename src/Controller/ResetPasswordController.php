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

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\ResetPasswordRequestRepository;
use App\Security\ResetPasswordFlowGuardTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordFlowGuardTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $em,
        private TranslatorInterface $translator,
        private ResetPasswordRequestRepository $resetRepo,
        private int $throttleLimit,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer): Response
    {
        // Reset flow state when entering the request page
        $this->resetFlowState($request);

        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        // Throttle BEFORE form submit
        $email = $form->get('email')->getData();
        if ($email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                $lastRequest = $this->resetRepo->getMostRecentResetRequest($user);

                if ($lastRequest) {
                    $requestedAt = $lastRequest->getRequestedAt()->getTimestamp();
                    $elapsed = time() - $requestedAt;

                    if ($elapsed < $this->throttleLimit) {
                        $remaining = $this->throttleLimit - $elapsed;
                        $minutes = ceil($remaining / 60);

                        $request->getSession()->set('reset_remaining_seconds', $remaining);

                        $this->addFlash('reset_password_error',
                            $this->translator->trans('reset.throttle_error', [
                                '%minutes%' => $minutes,
                            ], 'messages')
                        );

                        return $this->render('@theme/reset_password/request.html.twig', [
                            'requestForm' => $form,
                            'remainingSeconds' => $remaining,
                        ]);
                    }
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processSendingPasswordResetEmail(
                $form->get('email')->getData(),
                $mailer,
                $request
            );
        }

        return $this->render('@theme/reset_password/request.html.twig', [
            'requestForm' => $form,
            'remainingSeconds' => 0,
        ]);
    }

    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(Request $request): Response
    {
        if (!$this->isCheckEmailAllowed($request)) {
            $this->resetFlowState($request);

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $remaining = $request->getSession()->get('reset_remaining_seconds', 0);

        return $this->render('@theme/reset_password/check_email.html.twig', [
            'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
            'remainingSeconds' => $remaining,
        ]);
    }

    #[Route('/reset', name: 'app_reset_password_no_token')]
    public function resetNoToken(): RedirectResponse
    {
        return $this->redirectToRoute('app_forgot_password_request');
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, string $token): Response
    {
        // Protection: user must come from check-email
        if (!$this->isCheckEmailAllowed($request)) {
            $this->resetFlowState($request);

            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error',
                $this->translator->trans('reset.token_invalid', [], 'messages')
            );

            $this->resetFlowState($request);

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);

            $user->setPassword(
                $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
            $this->em->flush();

            $this->resetFlowState($request);

            return $this->redirectToRoute('home_index');
        }

        return $this->render('@theme/reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, Request $request): RedirectResponse
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $emailFormData]);

        if (!$user) {
            // Still allow check-email (security best practice)
            $this->allowCheckEmail($request);

            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->allowCheckEmail($request);

            return $this->redirectToRoute('app_check_email');
        }

        // Allow access to check-email and reset pages
        $this->allowCheckEmail($request);

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@oktodark.com', 'OktoDark no-reply'))
            ->to($user->getEmail())
            ->subject('Your password reset request')
            ->htmlTemplate('@theme/reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
            ]);

        $mailer->send($email);

        return $this->redirectToRoute('app_check_email');
    }
}
