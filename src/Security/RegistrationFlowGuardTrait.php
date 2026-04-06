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

use Symfony\Component\HttpFoundation\Request;

trait RegistrationFlowGuardTrait
{
    private const SESSION_REGISTER_ALLOWED = 'register_flow_allowed';
    private const SESSION_REGISTER_EMAIL = 'register_flow_email';

    protected function allowRegisterFlow(Request $request, string $email): void
    {
        $session = $request->getSession();
        $session->set(self::SESSION_REGISTER_ALLOWED, true);
        $session->set(self::SESSION_REGISTER_EMAIL, $email);
    }

    protected function denyRegisterFlow(Request $request): void
    {
        $session = $request->getSession();
        $session->remove(self::SESSION_REGISTER_ALLOWED);
        $session->remove(self::SESSION_REGISTER_EMAIL);
    }

    protected function isRegisterFlowAllowed(Request $request): bool
    {
        return true === $request->getSession()->get(self::SESSION_REGISTER_ALLOWED, false);
    }

    protected function getRegisterEmail(Request $request): ?string
    {
        return $request->getSession()->get(self::SESSION_REGISTER_EMAIL);
    }
}
