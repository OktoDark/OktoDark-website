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

use App\Entity\RegistrationWaitlist;
use App\Entity\User;
use App\Form\ProfileCompletionFormType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Security\FormAuthenticator;
use App\Security\RegistrationFlowGuardTrait;
use App\Service\EmailIdentityService;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    use RegistrationFlowGuardTrait;

    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly SettingsProvider $settingsProvider,
        private readonly RateLimiterFactory $resendVerificationLimiter,
    ) {
    }

    #[Route('/register/disabled', name: 'app_register_disabled')]
    public function registrationDisabled(Request $request, EntityManagerInterface $em): Response
    {
        if ($this->settingsProvider->isRegistrationEnabled()) {
            return $this->redirectToRoute('app_register');
        }

        if ($request->isMethod('POST')) {
            $email = mb_trim((string) $request->request->get('email'));

            if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Invalid email address.');
            } else {
                $session = $request->getSession();
                $last = $session->get('notify_last', 0);
                $now = time();

                if ($now - $last < 60) {
                    $this->addFlash('error', 'Please wait before submitting again.');
                } else {
                    $session->set('notify_last', $now);

                    $repo = $em->getRepository(RegistrationWaitlist::class);
                    $exists = $repo->findOneBy(['email' => $email]);

                    if ($exists) {
                        $this->addFlash('info', 'This email is already on the waiting list.');
                    } else {
                        $entry = new RegistrationWaitlist();
                        $entry->setEmail($email);
                        $em->persist($entry);
                        $em->flush();

                        $this->addFlash('success', 'You will be notified when registration opens.');
                    }
                }
            }
        }

        return $this->render('@theme/registration/disabled.html.twig', [
            'siteName' => $this->settingsProvider->getSiteName(),
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        EmailIdentityService $emailIdentity,
    ): Response {
        if (!$this->settingsProvider->isRegistrationEnabled()) {
            return $this->redirectToRoute('app_register_disabled');
        }

        $this->denyRegisterFlow($request);

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            $user->agreeTerms();

            $em->persist($user);
            $em->flush();

            $this->allowRegisterFlow($request, $user->getEmail());

            $siteName = $this->settingsProvider->getSiteName();

            $email = (new TemplatedEmail())
                ->from(new Address($emailIdentity->noreply(), $siteName))
                ->to($user->getEmail())
                ->subject('Please Confirm your Email')
                ->htmlTemplate('@email/registration/confirmation.html.twig');

            $email->getHeaders()->addTextHeader('X-Transport', 'no_reply');

            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                $email
            );

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render('@theme/registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/register/check-email', name: 'app_register_check_email')]
    public function checkEmailPage(Request $request): Response
    {
        if (!$this->settingsProvider->isRegistrationEnabled()) {
            return $this->redirectToRoute('app_register_disabled');
        }

        if (!$this->isRegisterFlowAllowed($request)) {
            return $this->redirectToRoute('app_register');
        }

        return $this->render('@theme/registration/check_email.html.twig', [
            'email' => $this->getRegisterEmail($request),
        ]);
    }

    #[Route('/verifyemail', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        UserRepository $repo,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $authenticator,
        FormAuthenticator $formAuthenticator,
    ): Response {
        if (!$this->settingsProvider->isRegistrationEnabled()) {
            return $this->redirectToRoute('app_register_disabled');
        }

        if (!$this->isRegisterFlowAllowed($request)) {
            return $this->redirectToRoute('app_register');
        }

        $userId = $request->query->get('id');

        if (!$userId) {
            $this->addFlash('verify_email_error', 'Invalid verification link.');

            return $this->redirectToRoute('app_register');
        }

        $user = $repo->find($userId);

        if (!$user) {
            $this->addFlash('verify_email_error', 'User not found.');

            return $this->redirectToRoute('app_register');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        return $authenticator->authenticateUser(
            $user,
            $formAuthenticator,
            $request
        );
    }

    #[Route('/resend-verification', name: 'app_resend_verification')]
    public function resendVerification(
        Request $request,
        UserRepository $repo,
        EntityManagerInterface $em,
        EmailIdentityService $emailIdentity,
    ): Response {
        if (!$this->settingsProvider->isRegistrationEnabled()) {
            return $this->redirectToRoute('app_register_disabled');
        }

        if (!$this->isRegisterFlowAllowed($request)) {
            return $this->redirectToRoute('app_register');
        }

        $email = $this->getRegisterEmail($request);

        if (!$email) {
            return $this->redirectToRoute('app_register');
        }

        $user = $repo->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('error', 'User not found.');

            return $this->redirectToRoute('app_register');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Your email is already verified.');

            return $this->redirectToRoute('security_login');
        }

        // RATE LIMITER
        $limiter = $this->resendVerificationLimiter->create($email);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->addFlash('error', 'Too many attempts. Please try again later.');

            return $this->redirectToRoute('app_register_check_email');
        }

        // COOLDOWN
        $session = $request->getSession();
        $lastSent = $session->get('last_verification_email', 0);
        $now = time();

        if ($now - $lastSent < 60) {
            $remaining = 60 - ($now - $lastSent);
            $this->addFlash('error', "Please wait {$remaining} seconds before resending.");

            return $this->redirectToRoute('app_register_check_email');
        }

        $session->set('last_verification_email', $now);

        // SEND EMAIL
        $siteName = $this->settingsProvider->getSiteName();

        $email = (new TemplatedEmail())
            ->from(new Address($emailIdentity->noreply(), $siteName))
            ->to($user->getEmail())
            ->subject('Please Confirm your Email')
            ->htmlTemplate('@email/registration/confirmation.html.twig');

        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            $email
        );

        $this->addFlash('success', 'Verification email resent.');

        return $this->redirectToRoute('app_register_check_email');
    }

    #[Route('/complete-profile', name: 'app_complete_profile')]
    public function completeProfile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('security_login');
        }

        if ($user->isActive()) {
            return $this->redirectToRoute('member_area');
        }

        $form = $this->createForm(ProfileCompletionFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setActive(true);
            $em->flush();

            return $this->redirectToRoute('member_area');
        }

        return $this->render('@theme/registration/complete.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }

    /* -------------------------------------------------------------
     * AJAX CHECKS
     * ------------------------------------------------------------- */
    #[Route('/check-username', name: 'check_username', methods: ['GET'])]
    public function checkUsername(Request $request, UserRepository $repo): JsonResponse
    {
        if (!$this->settingsProvider->isRegistrationEnabled()) {
            return $this->json(['exists' => true]);
        }

        $username = mb_trim((string) $request->query->get('username'));

        if ('' === $username) {
            return $this->json(['exists' => false]);
        }

        return $this->json([
            'exists' => null !== $repo->findOneBy(['username' => $username]),
        ]);
    }

    #[Route('/check-email', name: 'check_email', methods: ['GET'])]
    public function checkEmail(Request $request, UserRepository $repo): JsonResponse
    {
        if (!$this->settingsProvider->isRegistrationEnabled()) {
            return $this->json(['exists' => true]);
        }

        $email = mb_trim((string) $request->query->get('email'));

        if ('' === $email) {
            return $this->json(['exists' => false]);
        }

        return $this->json([
            'exists' => null !== $repo->findOneBy(['email' => $email]),
        ]);
    }
}
