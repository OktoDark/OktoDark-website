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

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[UniqueEntity(
    fields: ['email'],
    message: 'error.email_taken'
)]
#[UniqueEntity(
    fields: ['username'],
    message: 'error.username_taken'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    final public const ROLE_USER = 'ROLE_USER';
    final public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $location = null;

    // IMPORTANT: must be nullable for registration step 1
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = false;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $agreedTerms = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $newsletter = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $darkMode = false;

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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isActive(): bool
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

    public function getNewsletter(): ?bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(?bool $newsletter): self
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    public function getDarkMode(): ?bool
    {
        return $this->darkMode;
    }

    public function setDarkMode(?bool $darkMode): self
    {
        $this->darkMode = $darkMode;

        return $this;
    }

    public function isPending(): bool
    {
        return !$this->isVerified && !$this->active;
    }

    public function isVerifiedOnly(): bool
    {
        return $this->isVerified && !$this->active;
    }

    public function isFullyActive(): bool
    {
        return $this->isVerified && $this->active;
    }

    public function eraseCredentials(): void
    {
        // No temporary sensitive data stored
    }

    public function __serialize(): array
    {
        return [$this->id, $this->email, $this->password];
    }

    public function __unserialize(array $data): void
    {
        [$this->id, $this->email, $this->password] = $data;
    }

    public function __toString(): string
    {
        return (string) $this->getUserIdentifier();
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        return
            $this->password === $user->getPassword()
            && $this->getUserIdentifier() === $user->getUserIdentifier();
    }
}
