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

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class InviteMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private SettingsProvider $settingsProvider,
    ) {
    }

    public function sendInvite(string $email): void
    {
        $siteName = $this->settingsProvider->getSiteName();

        $message = (new TemplatedEmail())
            ->from(new Address('mailer@mailer.com', $siteName))
            ->to($email)
            ->subject('You are invited to join '.$siteName)
            ->htmlTemplate('@theme/emails/invite_waitlist.html.twig');

        $this->mailer->send($message);
    }
}
