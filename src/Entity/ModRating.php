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

use App\Repository\ModRatingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModRatingRepository::class)]
class ModRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Mods::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Mods $mod = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $rating;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $ip;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ============================
    // GETTERS & SETTERS
    // ============================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMod(): ?Mods
    {
        return $this->mod;
    }

    public function setMod(Mods $mod): self
    {
        $this->mod = $mod;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        // Optional: enforce 1–10 safety
        if ($rating < 1) {
            $rating = 1;
        }
        if ($rating > 10) {
            $rating = 10;
        }

        $this->rating = $rating;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
