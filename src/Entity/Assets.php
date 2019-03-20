<?php
/**
 * Copyright (c) 2019 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 16.03.2019 17:30
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AssetsRepository")
 * @ORM\Table(name="assets")
 */
class Assets
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

     /**
     *
     * From here start public functions in order as table
     *
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getSku(): ?string
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     */
    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    /**
     * Array brand
     */
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

    /**
     * @return array
     */
    public function getHairtype(): array
    {
        $hairtype = $this->hairtype;

        if (empty($hairtype)) {
            $hairtype[] = '-';
        }

        return array_unique($hairtype);
    }

    /**
     * @param array $hairtype
     */
    public function setHairtype(array $hairtype): void
    {
        $this->hairtype = $hairtype;
    }

    /**
     * @return array
     */
    public function getClothing(): array
    {
        $clothing = $this->clothing;

        if (empty($clothing)) {
            $clothing[] = '-';
        }

        return array_unique($clothing);
    }

    /**
     * @param array $clothing
     */
    public function setClothing(array $clothing): void
    {
        $this->clothing = $clothing;
    }

    /**
     * @return array
     */
    public function getMisc(): array
    {
        $misc = $this->misc;

        if (empty($misc)) {
            $misc[] = '-';
        }

        return array_unique($misc);
    }

    /**
     * @param array $misc
     */
    public function setMisc(array $misc): void
    {
        $this->misc = $misc;
    }

    /**
     * Requirement
     */
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

    /**
     * @return array
     */
    public function getSoftwarecompatible(): array
    {
        $softwarecompatible = $this->softwarecompatible;

        if (empty($softwarecompatible)) {
            $softwarecompatible[] = '-';
        }
        return array_unique($softwarecompatible);
    }

    /**
     * @param array $softwarecompatible
     */
    public function setSoftwarecompatible(array $softwarecompatible): void
    {
        $this->softwarecompatible = $softwarecompatible;
    }

    /**
     * @return array
     */
    public function getFigurecompatible(): array
    {
        $figurecompatible = $this->figurecompatible;

        if (empty($figurecompatible)) {
            $figurecompatible[] = '-';
        }

        return array_unique($figurecompatible);
    }

    /**
     * @param array $figurecompatible
     */
    public function setFigurecompatible(array $figurecompatible): void
    {
        $this->figurecompatible = $figurecompatible;
    }

    /**
     * @return array
     */
    public function getGenre(): array
    {
        $genre = $this->genre;

        if (empty($genre)) {
            $genre[] = '-';
        }

        return array_unique($genre);
    }

    /**
     * @param array $genre
     */
    public function setGenre(array $genre): void
    {
        $this->genre = $genre;
    }

    /**
     * @return bool
     */
    public function isBundle(): bool
    {
        return $this->bundle;
    }

    /**
     * @param bool $bundle
     */
    public function setBundle(bool $bundle): void
    {
        $this->bundle = $bundle;
    }
}
