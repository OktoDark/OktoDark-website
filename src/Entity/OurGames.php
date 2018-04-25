<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OurGamesRepository")
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
     * @ORM\Column(type="text", length=100)
     */
    private $ourGameName;

    /**
     * @ORM\Column(type="text", length=100)
     */
    private $ourGameURL;

    /**
     * @ORM\Column(type="text", length=100)
     */
    private $ourGameURLCDN;

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
     * @ORM\Column(type="text", length=100)
     */
    private $ourGameCover;

    /**
     * @ORM\Column(type="text", length=100)
     */
    private $ourGameShortTitle;

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

    public function getId()
    {
        return $this->id;
    }
}
