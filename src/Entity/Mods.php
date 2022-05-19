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

use App\Repository\ModsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ModsRepository::class)]
#[ORM\Table(name: 'mods')]
class Mods
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 100)]
    private ?string $slug = null;

    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    private array $compatible = [];

    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 500)]
    private $download;

    #[ORM\JoinTable(name: 'created_at')]
    #[ORM\Column(type: 'datetime')]
    #[ORM\JoinColumn(nullable: true)]
    private $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCompatible()
    {
        return $this->compatible;
    }

    public function setCompatible($compatible): void
    {
        $this->compatible = $compatible;
    }

    public function getDownload(): string
    {
        return $this->download;
    }

    public function setDownload(string $download): void
    {
        $this->download = $download;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
