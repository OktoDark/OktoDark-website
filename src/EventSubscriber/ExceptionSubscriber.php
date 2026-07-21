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

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Handle both 403 exception types
        if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException) {
            $this->handleAccessDenied($event, $exception);

            return;
        }

        // Only handle 404 errors
        if (!$exception instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        $referer = $request->headers->get('referer');

        if ($referer) {
            $event->setResponse(new RedirectResponse($referer));

            return;
        }

        $event->setResponse(new RedirectResponse('/'));
    }

    private function handleAccessDenied(ExceptionEvent $event, \Throwable $exception): void
    {
        $requiredPermission = $exception->getMessage();

        $content = $this->twig->render('@theme/errors/error403.html.twig', [
            'required_permission' => $requiredPermission,
        ]);

        $event->setResponse(new Response($content, 403));
    }
}
