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
 * @ORM\Entity(repositoryClass="App\Repository\CareersRepository")
 * @ORM\Table(name="careers")
 */
class Careers
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
    private $jobtitle;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="1000")
     */
    private $requiments;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="3")
     */
    private $number;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getJobtitle()
    {
        return $this->jobtitle;
    }

    /**
     * @param mixed $jobtitle
     */
    public function setJobtitle($jobtitle)
    {
        $this->jobtitle = $jobtitle;
    }

    /**
     * @return mixed
     */
    public function getRequiments()
    {
        return $this->requiments;
    }

    /**
     * @param mixed $requiments
     */
    public function setRequiments($requiments)
    {
        $this->requiments = $requiments;
    }

    /**
     * @return mixed
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param mixed $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }
}
