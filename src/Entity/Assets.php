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

use App\Repository\AssetsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetsRepository::class)]
#[ORM\Table(name: 'assets')]
class Assets
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $sku = null;

    #[ORM\Column(type: Types::JSON)]
    private array $brand = [];

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\Length(min: 10, max: 10000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Length(min: 10, max: 50)]
    private ?string $website = null;

    #[ORM\Column(type: Types::JSON)]
    private array $hairtype = [];

    #[ORM\Column(type: Types::JSON)]
    private array $clothing = [];

    #[ORM\Column(type: Types::JSON)]
    private array $misc = [];

    #[ORM\Column(type: Types::JSON)]
    private array $requirement = [];

    #[ORM\Column(type: Types::JSON)]
    private array $softwarecompatible = [];

    #[ORM\Column(type: Types::JSON)]
    private array $figurecompatible = [];

    #[ORM\Column(type: Types::JSON)]
    private array $genre = [];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $bundle = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSku(): ?int
    {
        return $this->sku;
    }

    public function setSku(int $sku): void
    {
        $this->sku = $sku;
    }

    public function getBrand(): array
    {
        return $this->brand ?: ['-'];
    }

    public function setBrand(array $brand): void
    {
        $this->brand = $brand;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(string $website): void
    {
        $this->website = $website;
    }

    public function getHairtype(): array
    {
        return $this->hairtype ?: ['-'];
    }

    public function setHairtype(array $hairtype): void
    {
        $this->hairtype = $hairtype;
    }

    public function getClothing(): array
    {
        return $this->clothing ?: ['-'];
    }

    public function setClothing(array $clothing): void
    {
        $this->clothing = $clothing;
    }

    public function getMisc(): array
    {
        return $this->misc ?: ['-'];
    }

    public function setMisc(array $misc): void
    {
        $this->misc = $misc;
    }

    public function getRequirement(): array
    {
        return $this->requirement ?: ['-'];
    }

    public function setRequirement(array $requirement): void
    {
        $this->requirement = $requirement;
    }

    public function getSoftwarecompatible(): array
    {
        return $this->softwarecompatible ?: ['-'];
    }

    public function setSoftwarecompatible(array $softwarecompatible): void
    {
        $this->softwarecompatible = $softwarecompatible;
    }

    public function getFigurecompatible(): array
    {
        return $this->figurecompatible ?: ['-'];
    }

    public function setFigurecompatible(array $figurecompatible): void
    {
        $this->figurecompatible = $figurecompatible;
    }

    public function getGenre(): array
    {
        return $this->genre ?: ['-'];
    }

    public function setGenre(array $genre): void
    {
        $this->genre = $genre;
    }

    public function isBundle(): bool
    {
        return $this->bundle;
    }

    public function setBundle(bool $bundle): void
    {
        $this->bundle = $bundle;
    }
}
