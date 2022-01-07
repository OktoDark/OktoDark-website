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
    private $id = null;

    /**
     * @ORM\Column(type="string")
     */
    #[Assert\Length(max: 10)]
    private ?string $position = null;

    /**
     * @ORM\Column(type="string")
     */
    #[Assert\Length(max: 20)]
    private ?string $name = null;

    /**
     * @ORM\Column(type="string")
     */
    #[Assert\Length(max: 100)]
    private ?string $description = null;

    /**
     * @ORM\Column(type="string")
     */
    #[Assert\Length(max: 10)]
    private ?string $teamImage = null;

    public function getId()
    {
        return $this->id;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setPosition($position)
    {
        $this->position = $position;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getTeamImage()
    {
        return $this->teamImage;
    }

    public function setTeamImage($teamImage)
    {
        $this->teamImage = $teamImage;
    }
}
