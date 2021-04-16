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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ServicesRepository")
 * @ORM\Table(name="services")
 */
class Services
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
     * @Assert\Length(max="100")
     */
    private $serviceName;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="20")
     */
    private $servicePrice;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="10")
     */
    private $serviceWorkDays;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="10000")
     */
    private $ServiceToDo;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function setServiceName(?string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    public function getServicePrice(): ?string
    {
        return $this->servicePrice;
    }

    public function setServicePrice(?string $servicePrice): void
    {
        $this->servicePrice = $servicePrice;
    }

    public function getServiceWorkDays(): ?string
    {
        return $this->serviceWorkDays;
    }

    public function setServiceWorkDays(?string $serviceWorkDays): void
    {
        $this->serviceWorkDays = $serviceWorkDays;
    }

    public function getServiceToDo(): ?string
    {
        return $this->ServiceToDo;
    }

    public function setServiceToDo(?string $ServiceToDo): void
    {
        $this->ServiceToDo = $ServiceToDo;
    }
}
