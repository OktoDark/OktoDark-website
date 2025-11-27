<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Utils;

use function Symfony\Component\String\u;

class Validator
{
    public function validateUsername(?string $username): string
    {
        if (empty($username)) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }

        if (1 !== preg_match('/^[a-z_]+$/', $username)) {
            throw new \InvalidArgumentException('The username must contain only lowercase latin characters and underscores.');
        }

        return $username;
    }

    public function validatePassword(?string $plainPassword): string
    {
        if (empty($plainPassword)) {
            throw new \InvalidArgumentException('The password cannot be empty.');
        }

        if (u($plainPassword)->trim()->length() < 6) {
            throw new \InvalidArgumentException('The password must be at least 6 characters long.');
        }

        return $plainPassword;
    }

    public function validateEmail(?string $email): string
    {
        if (empty($email)) {
            throw new \InvalidArgumentException('The email cannot be empty.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('The email is not valid.');
        }

        return $email;
    }

    public function validateFullName(?string $fullName): string
    {
        if (empty($fullName)) {
            throw new \InvalidArgumentException('The full name cannot be empty.');
        }

        return $fullName;
    }
}
