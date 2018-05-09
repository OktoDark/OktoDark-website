<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

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
     * @ORM\Column(type="text")
     * @Assert\Length(max="100")
     */
    private $jobtitle;

    /**
     * @ORM\Column(type="text")
     * @Assert\Length(max="1000")
     */
    private $requiments;

    /**
     * @ORM\Column(type="text")
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
