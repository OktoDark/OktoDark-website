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

use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contact_messages')]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\Email]
    #[Assert\NotBlank]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $department = null; // Stored as 'staff' or 'webmaster'

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $replyMessage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $repliedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // --- MAPPING FIX ---
    // Since your form uses "category", we add this helper
    public function getCategory(): ?string
    {
        return $this->department;
    }

    public function setCategory(string $category): self
    {
        $this->department = $category;

        return $this;
    }

    // --- GETTERS & SETTERS ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(string $dept): self
    {
        $this->department = $dept;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getReplyMessage(): ?string
    {
        return $this->replyMessage;
    }

    public function setReplyMessage(?string $reply): self
    {
        $this->replyMessage = $reply;

        return $this;
    }

    public function getRepliedAt(): ?\DateTimeInterface
    {
        return $this->repliedAt;
    }

    public function setRepliedAt(?\DateTimeInterface $date): self
    {
        $this->repliedAt = $date;

        return $this;
    }
}
