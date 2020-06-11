<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 13.01.2020, 06:50
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AssetsRepository")
 * @ORM\Table(name="assets")
 */
class Assets implements \Serializable
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @Assert\Length(min=2, max=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="integer")
     */
    private $sku;

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $brand = [];

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     * @Assert\Length(min=10, max=10000)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(min=10, max=50)
     */
    private $website;

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $hairtype = [];

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    private $clothing = [];

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    private $misc = [];

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $requirement = [];

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $softwarecompatible = [];

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $figurecompatible = [];

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $genre = [];

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $bundle = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    public function getBrand(): array
    {
        $brand = $this->brand;

        if (empty($brand)) {
            $brand[] = '-';
        }

        return array_unique($brand);
    }

    public function setBrand(array $brand): void
    {
        $this->brand = $brand;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
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
        $hairtype = $this->hairtype;

        if (empty($hairtype)) {
            $hairtype[] = '-';
        }

        return array_unique($hairtype);
    }

    public function setHairtype(array $hairtype): void
    {
        $this->hairtype = $hairtype;
    }

    public function getClothing(): array
    {
        $clothing = $this->clothing;

        if (empty($clothing)) {
            $clothing[] = '-';
        }

        return array_unique($clothing);
    }

    public function setClothing(array $clothing): void
    {
        $this->clothing = $clothing;
    }

    public function getMisc(): array
    {
        $misc = $this->misc;

        if (empty($misc)) {
            $misc[] = '-';
        }

        return array_unique($misc);
    }

    public function setMisc(array $misc): void
    {
        $this->misc = $misc;
    }

    public function getRequirement(): array
    {
        $requirement = $this->requirement;

        if (empty($requirement)) {
            $requirement[] = '-';
        }

        return array_unique($requirement);
    }

    public function setRequirement(array $requirement): void
    {
        $this->requirement = $requirement;
    }

    public function getSoftwarecompatible(): array
    {
        $softwarecompatible = $this->softwarecompatible;

        if (empty($softwarecompatible)) {
            $softwarecompatible[] = '-';
        }

        return array_unique($softwarecompatible);
    }

    public function setSoftwarecompatible(array $softwarecompatible): void
    {
        $this->softwarecompatible = $softwarecompatible;
    }

    public function getFigurecompatible(): array
    {
        $figurecompatible = $this->figurecompatible;

        if (empty($figurecompatible)) {
            $figurecompatible[] = '-';
        }

        return array_unique($figurecompatible);
    }

    public function setFigurecompatible(array $figurecompatible): void
    {
        $this->figurecompatible = $figurecompatible;
    }

    public function getGenre(): array
    {
        $genre = $this->genre;

        if (empty($genre)) {
            $genre[] = '-';
        }

        return array_unique($genre);
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

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        // add $this->salt too if you don't use Bcrypt or Argon2i
        return serialize([$this->id, $this->username, $this->password]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        // add $this->salt too if you don't use Bcrypt or Argon2i
        [$this->id, $this->username, $this->password] = unserialize($serialized, ['allowed_classes' => false]);
    }
}
