<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

// src/Twig/PermissionExtension.php

namespace App\Twig;

use App\Security\PermissionChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PermissionExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private PermissionChecker $checker,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can', [$this, 'can']),
        ];
    }

    public function can(string $permission): bool
    {
        // Use TokenStorage directly to get user from token (even if not fully authenticated)
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user) {
            return false;
        }

        return $this->checker->userHasPermission($user, $permission);
    }
}
