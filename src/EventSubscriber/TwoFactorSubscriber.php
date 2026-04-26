<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

final class TwoFactorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private RouterInterface $router,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        // Allowed routes during 2FA
        $allowed = [
            'security_login',
            'login_2fa_verify',
            'login_2fa_resend',
            'security_logout',
        ];

        $route = $request->attributes->get('_route');

        // 1) If route is allowed → do nothing
        if (!$route || in_array($route, $allowed, true)) {
            return;
        }

        // 2) If user is NOT authenticated → do nothing
        if (!$this->security->getUser()) {
            return;
        }

        // 3) If 2FA is NOT pending → do nothing
        if (true !== $session->get('2fa_pending')) {
            return;
        }

        // 4) Otherwise → force redirect to verify page
        $event->setResponse(
            new \Symfony\Component\HttpFoundation\RedirectResponse(
                $this->router->generate('login_2fa_verify')
            )
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => 'onKernelRequest',
        ];
    }
}
