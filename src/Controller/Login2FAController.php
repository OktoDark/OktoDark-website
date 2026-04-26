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
use App\Repository\UserRepository;
use App\Service\Login2FAService;
use App\Service\TrustedDeviceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class Login2FAController extends AbstractController
{
    #[Route('/login/verify', name: 'login_2fa_verify', methods: ['GET', 'POST'])]
    public function verify(
        Request $request,
        UserRepository $users,
        Login2FAService $login2FA,
        TrustedDeviceService $trustedDevices,
        EntityManagerInterface $em,
    ): Response {
        $session = $request->getSession();

        // User must have passed password step
        $userId = $session->get('2fa_user_id');
        if (!$userId) {
            return $this->redirectToRoute('security_login');
        }

        /** @var User|null $user */
        $user = $users->find($userId);
        if (!$user) {
            $session->remove('2fa_user_id');
            $session->remove('2fa_pending');

            return $this->redirectToRoute('security_login');
        }

        // GET → show verify page
        if ($request->isMethod('GET')) {
            return $this->render('@theme/security/login_2fa.html.twig', [
                'user' => $user,
            ]);
        }

        // POST → verify code
        $submittedCode = trim($request->request->get('code', ''));

        if (!$login2FA->isCodeValid($user, $submittedCode)) {
            $this->addFlash('error', 'Invalid or expired code.');

            return $this->redirectToRoute('login_2fa_verify');
        }

        // Code is valid → clear 2FA pending
        $session->remove('2fa_pending');

        // TRUST DEVICE?
        if ('1' === $request->request->get('trust_device') && $user->isTrustedDevicesEnabled()) {
            $trustedDevices->addTrustedDevice($user, $request);
            $em->flush();
        }

        // Authenticate user manually
        $token = new UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles()
        );

        $this->container->get('security.token_storage')->setToken($token);
        $session->set('_security_main', serialize($token));

        // Redirect to original target path if exists
        $target = $session->get('2fa_target_path');
        if ($target) {
            $session->remove('2fa_target_path');

            return $this->redirect($target);
        }

        // Default redirect
        return $this->redirectToRoute('member_area');
    }
}
