<?php
/**
 * Copyright (c) 2019 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 27.06.2019 19:43
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

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    /**
     * @param string $serviceName
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return string|null
     */
    public function getServicePrice(): ?string
    {
        return $this->servicePrice;
    }

    /**
     * @param string $servicePrice
     */
    public function setServicePrice(string $servicePrice): void
    {
        $this->servicePrice = $servicePrice;
    }

    /**
     * @return string|null
     */
    public function getServiceWorkDays(): ?string
    {
        return $this->serviceWorkDays;
    }

    /**
     * @param string $serviceWorkDays
     */
    public function setServiceWorkDays(string $serviceWorkDays): void
    {
        $this->serviceWorkDays = $serviceWorkDays;
    }

    /**
     * @return string|null
     */
    public function getServiceToDo(): ?string
    {
        return $this->ServiceToDo;
    }

    /**
     * @param string $ServiceToDo
     */
    public function setServiceToDo(string $ServiceToDo): void
    {
        $this->ServiceToDo = $ServiceToDo;
    }
}
