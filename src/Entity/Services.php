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

use App\Repository\ServicesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServicesRepository::class)]
#[ORM\Table(name: 'services')]
class Services
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $serviceName = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 20)]
    private ?string $servicePrice = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 10)]
    private ?string $serviceWorkDays = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 10000)]
    private ?string $ServiceToDo = null;

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
