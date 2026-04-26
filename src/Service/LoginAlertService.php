<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class LoginAlertService
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailIdentityService $emailIdentity,
    ) {
    }

    public function sendLoginAlert(User $user, string $ip, string $ua, ?string $location = null): void
    {
        if (!$user->isLoginAlertsEnabled()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->emailIdentity->noreply())
            ->to($user->getEmail())
            ->subject('New login to your OktoDark account')
            ->htmlTemplate('emails/login_alert.html.twig')
            ->context([
                'user' => $user,
                'ip' => $ip,
                'userAgent' => $ua,
                'location' => $location,
                'loggedAt' => new \DateTime(),
            ]);

        $email->getHeaders()->addTextHeader('X-Transport', 'no_reply');

        $this->mailer->send($email);
    }
}
