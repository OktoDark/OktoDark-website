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

use App\Entity\LoginCode;
use App\Entity\User;
use App\Repository\LoginCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class Login2FAService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoginCodeRepository $codes,
        private MailerInterface $mailer,
        private EmailIdentityService $emailIdentity,
    ) {
    }

    public function generateCode(User $user): LoginCode
    {
        // Invalidate old codes
        foreach ($this->codes->findBy(['user' => $user]) as $old) {
            $this->em->remove($old);
        }

        $code = mb_str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $loginCode = (new LoginCode())
            ->setUser($user)
            ->setCode($code)
            ->setExpiresAt(new \DateTime('+5 minutes'));

        $this->em->persist($loginCode);
        $this->em->flush();

        return $loginCode;
    }

    public function sendCodeEmail(User $user, LoginCode $code): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->emailIdentity->noreply(), 'OktoDark no-reply'))
            ->to($user->getEmail())
            ->subject('Your OktoDark login code')
            ->htmlTemplate('emails/login_code.html.twig')
            ->context([
                'user' => $user,
                'code' => $code->getCode(),
            ]);

        $email->getHeaders()->addTextHeader('X-Transport', 'no_reply');

        $this->mailer->send($email);
    }

    /**
     * Symfony 8+ controller expects this exact method name.
     */
    public function isCodeValid(User $user, string $input): bool
    {
        $code = $this->codes->findOneBy(['user' => $user]);

        if (!$code) {
            return false;
        }

        if ($code->isExpired()) {
            return false;
        }

        if ($code->getAttempts() >= 5) {
            return false;
        }

        if (!hash_equals($code->getCode(), $input)) {
            $code->incrementAttempts();
            $this->em->flush();

            return false;
        }

        // Success → remove code
        $this->em->remove($code);
        $this->em->flush();

        return true;
    }
}
