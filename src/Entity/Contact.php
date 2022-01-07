<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

class Contact
{
    /**
     * @ORM\Column(type="string")
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $staff = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $webmaster = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $email = null;

    private array $category = [];

    /**
     * @ORM\Column(type="string")
     */
    private ?string $subject = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $message = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getStaff()
    {
        return $this->staff;
    }

    public function setStaff($staff): void
    {
        $this->staff = $staff;
    }

    public function getWebmaster()
    {
        return $this->webmaster;
    }

    public function setWebmaster($webmaster): void
    {
        $this->webmaster = $webmaster;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory($category): void
    {
        $this->category = $category;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
