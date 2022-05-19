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

use App\Repository\CareersRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CareersRepository::class)]
#[ORM\Table(name: 'careers')]
class Careers
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 100)]
    private ?string $jobtitle = null;

    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 100)]
    private ?string $requiments = null;

    #[ORM\Column(type: 'string')]
    #[Assert\Length(max: 3)]
    private ?string $number = null;

    public function getId()
    {
        return $this->id;
    }

    public function getJobtitle()
    {
        return $this->jobtitle;
    }

    public function setJobtitle($jobtitle)
    {
        $this->jobtitle = $jobtitle;
    }

    public function getRequiments()
    {
        return $this->requiments;
    }

    public function setRequiments($requiments)
    {
        $this->requiments = $requiments;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function setNumber($number)
    {
        $this->number = $number;
    }
}
