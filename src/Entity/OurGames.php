<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OurGamesRepository")
 * @ORM\Table(name="our_games")
 */
class OurGames
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
    private $ourGameName;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameURL;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameURLCDN;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameCover;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameShortTitle;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getOurGameURLCDN()
    {
        return $this->ourGameURLCDN;
    }

    /**
     * @param mixed $ourGameURLCDN
     */
    public function setOurGameURLCDN($ourGameURLCDN)
    {
        $this->ourGameURLCDN = $ourGameURLCDN;
    }

    /**
     * @return mixed
     */
    public function getOurGameName()
    {
        return $this->ourGameName;
    }

    /**
     * @param mixed $ourGameName
     */
    public function setOurGameName($ourGameName)
    {
        $this->ourGameName = $ourGameName;
    }

    /**
     * @return mixed
     */
    public function getOurGameURL()
    {
        return $this->ourGameURL;
    }

    /**
     * @param mixed $ourGameURL
     */
    public function setOurGameURL($ourGameURL)
    {
        $this->ourGameURL = $ourGameURL;
    }

    /**
     * @return mixed
     */
    public function getOurGameCover()
    {
        return $this->ourGameCover;
    }

    /**
     * @param mixed $ourGameCover
     */
    public function setOurGameCover($ourGameCover)
    {
        $this->ourGameCover = $ourGameCover;
    }

    /**
     * @return mixed
     */
    public function getOurGameShortTitle()
    {
        return $this->ourGameShortTitle;
    }

    /**
     * @param mixed $ourGameShortTitle
     */
    public function setOurGameShortTitle($ourGameShortTitle)
    {
        $this->ourGameShortTitle = $ourGameShortTitle;
    }
}
