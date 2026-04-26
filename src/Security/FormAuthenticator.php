<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Security;

use App\Entity\AccountActivity;
use App\Entity\User;
use App\Service\AccountActivityLogger;
use App\Service\DeviceParserService;
use App\Service\GeoIpService;
use App\Service\Login2FAService;
use App\Service\TrustedDeviceService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'security_login';

    private readonly UrlGeneratorInterface $urlGenerator;
    private readonly CsrfTokenManagerInterface $csrfTokenManager;
    private readonly Login2FAService $login2FA;
    private readonly TrustedDeviceService $trustedDevices;
    private readonly AccountActivityLogger $activityLogger;
    private readonly DeviceParserService $deviceParser;
    private readonly GeoIpService $geoIP;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        Login2FAService $login2FA,
        TrustedDeviceService $trustedDevices,
        AccountActivityLogger $activityLogger,
        DeviceParserService $deviceParser,
        GeoIpService $geoIP,
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->login2FA = $login2FA;
        $this->trustedDevices = $trustedDevices;
        $this->activityLogger = $activityLogger;
        $this->deviceParser = $deviceParser;
        $this->geoIP = $geoIP;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('x_token_98342');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        $badges = [
            new CsrfTokenBadge('security_login', $csrfToken),
        ];

        if ($request->request->get('_remember_me')) {
            $badges[] = new RememberMeBadge();
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            $badges
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $session = $request->getSession();
        $locale = $request->getLocale();

        $ua = $request->headers->get('User-Agent');
        $parsed = $this->deviceParser->parse($ua);
        $ip = $request->getClientIp();
        $country = $this->geoIP->getCountryCode($ip);

        /*
         * CASE 0 — User not active → force profile completion
         * (used after email verification auto-login as well)
         */
        if (!$user->isActive()) {
            return new RedirectResponse(
                $this->urlGenerator->generate('app_complete_profile', ['_locale' => $locale])
            );
        }

        /*
         * CASE A — Device is trusted → full login
         */
        if ($this->trustedDevices->hasTrustedDevice($user, $request)) {
            // Log successful login
            $this->activityLogger->log(
                $user,
                AccountActivity::TYPE_LOGIN_SUCCESS,
                [
                    'ip' => $ip,
                    'country' => $country,
                    'device' => $parsed['label'],
                    'icon' => $parsed['icon'],
                ]
            );

            // Restore target path
            if ($targetPath = $this->getTargetPath($session, $firewallName)) {
                return new RedirectResponse($targetPath);
            }

            // Admin redirect
            if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return new RedirectResponse(
                    $this->urlGenerator->generate('admin_dashboard', ['_locale' => $locale])
                );
            }

            // Normal user redirect
            return new RedirectResponse(
                $this->urlGenerator->generate('member_area', ['_locale' => $locale])
            );
        }

        /*
         * CASE B — Device NOT trusted → start 2FA
         */

        // Log login attempt requiring 2FA
        $this->activityLogger->log(
            $user,
            AccountActivity::TYPE_LOGIN_FAILED,
            [
                'reason' => 'untrusted_device',
                'ip' => $ip,
                'country' => $country,
                'device' => $parsed['label'],
                'icon' => $parsed['icon'],
            ]
        );

        // Generate + send code
        $code = $this->login2FA->generateCode($user);
        $this->login2FA->sendCodeEmail($user, $code);

        // Mark 2FA pending
        $session->set('2fa_user_id', $user->getId());
        $session->set('2fa_pending', true);

        // Store target path
        if ($targetPath = $this->getTargetPath($session, $firewallName)) {
            $session->set('2fa_target_path', $targetPath);
        }

        return new RedirectResponse(
            $this->urlGenerator->generate('login_2fa_verify', ['_locale' => $locale])
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE, [
            '_locale' => $request->getLocale(),
        ]);
    }

    public function supportsRememberMe(): bool
    {
        return true;
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && self::LOGIN_ROUTE === $request->attributes->get('_route');
    }
}
