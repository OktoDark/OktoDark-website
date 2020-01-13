<?php
/**
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
 * @ORM\Entity(repositoryClass="App\Repository\TeamRepository")
 * @ORM\Table(name="team")
 */
class Team
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
     * @Assert\Length(max="10")
     */
    private $position;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="20")
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="10")
     */
    private $teamImage;

    public function getId()
    {
        return $this->id;
    }

    /**
    * @return mixed
    */
    public function getPosition()
    {
        return $this->position;
    }

    /**
    * @param mixed $position
    */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    /**
    * @return mixed
    */
    public function getName()
    {
        return $this->name;
    }

    /**
    * @param mixed $name
    */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
    * @return mixed
    */
    public function getDescription()
    {
        return $this->description;
    }

    /**
    * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
    * @return mixed
    */
    public function getTeamImage()
    {
        return $this->teamImage;
    }

    /**
    * @param mixed $teamImage
    */
    public function setTeamImage($teamImage)
    {
        $this->teamImage = $teamImage;
    }
}
