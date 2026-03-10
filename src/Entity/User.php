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

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @UniqueEntity(fields={"email"}, message="There is already an account with this email")
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    final public const ROLE_USER = 'ROLE_USER';
    final public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $password = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private $active;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $agreedTerms = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isVerified = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $name): self
    {
        $this->firstName = $name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $name): self
    {
        $this->lastName = $name;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    public function isActive()
    {
        return $this->active;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (empty($roles)) {
            $roles[] = self::ROLE_USER;
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getAgreedTerms(): ?\DateTimeInterface
    {
        return $this->agreedTerms;
    }

    public function agreeTerms(): void
    {
        $this->agreedTerms = new \DateTime();
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * IMPORTANT: We must serialize the EMAIL because that is our identifier.
     */
    public function __serialize(): array
    {
        return [$this->id, $this->email, $this->password];
    }

    public function __unserialize(array $data): void
    {
        [$this->id, $this->email, $this->password] = $data;
    }

    public function __toString()
    {
        return (string) $this->getUserIdentifier();
    }

    /**
     * EquatableInterface method.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        // Check password
        if ($this->password !== $user->getPassword()) {
            return false;
        }

        // Check email/identifier
        if ($this->getUserIdentifier() !== $user->getUserIdentifier()) {
            return false;
        }

        return true;
    }
}
