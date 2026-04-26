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

use App\Repository\UserRepository;
use App\Service\Login2FAService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    /**
     * LOGIN PAGE (/login)
     * GET  → show login form
     * POST → handled by FormAuthenticator.
     */
    #[Route('/login', name: 'security_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        // Already logged in → redirect
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('member_area');
        }

        $error = $auth->getLastAuthenticationError();
        $lastUsername = $auth->getLastUsername();

        $response = $this->render('@theme/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);

        // Prevent caching (Symfony 8 still needs this for login pages)
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * LOGOUT
     * Handled by Symfony firewall.
     */
    #[Route('/logout', name: 'security_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the firewall.');
    }

    /**
     * RESEND 2FA CODE (/login/verify/resend).
     */
    #[Route('/login/verify/resend', name: 'login_2fa_resend', methods: ['POST'])]
    public function resend(
        Request $request,
        UserRepository $users,
        Login2FAService $login2FA,
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('2fa_user_id');

        if (!$userId) {
            return $this->redirectToRoute('security_login');
        }

        $user = $users->find($userId);

        if (!$user || !$user->isTwofaResendEnabled()) {
            return $this->redirectToRoute('login_2fa_verify');
        }

        $code = $login2FA->generateCode($user);
        $login2FA->sendCodeEmail($user, $code);

        $this->addFlash('success', 'A new code has been sent.');

        return $this->redirectToRoute('login_2fa_verify');
    }
}
