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

/**
 * @ORM\Entity(repositoryClass="App\Repository\AssetsRepository")
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
     * @ORM\Column(type="text", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="text", length=10000)
     */
    private $description;

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $category;

    /**
     * @var string
     *
     * @ORM\Column(type="integer")
     */
    private $ids;

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $subcat;

    /**
     * @var array
     *
     * @ORM\Column(type="json")
     */
    private $requirement;

    /**
     * @var string
     *
     * @ORM\Column(type="text", length=500)
     */
    private $url;

    /**
     * @var string
     *
     * @ORM\Column(type="text", length=255)
     */
    private $from;

    /**
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    private $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
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
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return array
     */
    public function getCategory(): array
    {
        return $this->category;
    }

    /**
     * @param array $category
     */
    public function setCategory(array $category): void
    {
        $this->category = $category;
    }

    /**
     * @return string
     */
    public function getIds(): string
    {
        return $this->ids;
    }

    /**
     * @param string $ids
     */
    public function setIds(string $ids): void
    {
        $this->ids = $ids;
    }

    /**
     * @return array
     */
    public function getSubcat(): array
    {
        return $this->subcat;
    }

    /**
     * @param array $subcat
     */
    public function setSubcat(array $subcat): void
    {
        $this->subcat = $subcat;
    }

    /**
     * @return array
     */
    public function getRequirement(): array
    {
        return $this->requirement;
    }

    /**
     * @param array $requirement
     */
    public function setRequirement(array $requirement): void
    {
        $this->requirement = $requirement;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * @param string $from
     */
    public function setFrom(string $from): void
    {
        $this->from = $from;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt): void
    {
        $this->createdAt = $createdAt;
    }




}
