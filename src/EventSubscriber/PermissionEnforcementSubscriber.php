<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\EventSubscriber;

use App\Security\Attribute\Permission as PermissionAttribute;
use App\Security\PermissionChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PermissionEnforcementSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PermissionChecker $permissionChecker,
        private Security $security,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!\is_array($controller)) {
            return;
        }

        [$controllerObject, $methodName] = $controller;

        if (!\is_object($controllerObject)) {
            return;
        }

        $reflection = new \ReflectionClass($controllerObject);
        $permissions = [];

        // Collect class-level #[Permission] attributes
        foreach ($reflection->getAttributes(PermissionAttribute::class) as $attr) {
            $permissions[] = $attr->newInstance()->name;
        }

        // Collect method-level #[Permission] attributes
        if ($methodName && $reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);
            foreach ($method->getAttributes(PermissionAttribute::class) as $attr) {
                $permissions[] = $attr->newInstance()->name;
            }
        }

        if (empty($permissions)) {
            return;
        }

        // Use TokenStorage directly to get user from token (even if not fully authenticated)
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user) {
            throw new AccessDeniedException('You must be logged in to access this resource.');
        }

        foreach ($permissions as $permission) {
            if ($this->permissionChecker->userHasPermission($user, $permission)) {
                return; // User has at least one of the required permissions
            }
        }

        throw new AccessDeniedException('You do not have permission to access this resource.');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }
}
