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

trait ResetPasswordFlowGuardTrait
{
    private const SESSION_ALLOW_CHECK_EMAIL = 'reset_flow_allowed';

    protected function allowCheckEmail(Request $request): void
    {
        $request->getSession()->set(self::SESSION_ALLOW_CHECK_EMAIL, true);
    }

    protected function denyCheckEmail(Request $request): void
    {
        $request->getSession()->remove(self::SESSION_ALLOW_CHECK_EMAIL);
    }

    protected function isCheckEmailAllowed(Request $request): bool
    {
        return true === $request->getSession()->get(self::SESSION_ALLOW_CHECK_EMAIL, false);
    }

    protected function resetFlowState(Request $request): void
    {
        $session = $request->getSession();
        $session->remove(self::SESSION_ALLOW_CHECK_EMAIL);
        $session->remove('reset_remaining_seconds');
    }
}
