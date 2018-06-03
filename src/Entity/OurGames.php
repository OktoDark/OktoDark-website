<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OurGamesRepository")
 * @ORM\Table(name="our_games")
 *
 * Here will be all for games, downloading links for alpha/beta stages for our members.
 * Needed to be reworked all database for a clean sheets.
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
    private $ourGameShortName;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameWebsiteLink;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameFreeLink;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameAlpha;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameBeta;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGameSourceCode;

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

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getOurGameName(): string
    {
        return $this->ourGameName;
    }

    /**
     * @param string $ourGameName
     */
    public function setOurGameName(string $ourGameName)
    {
        $this->ourGameName = $ourGameName;
    }

    /**
     * @return string
     */
    public function getOurGameShortName(): string
    {
        return $this->ourGameShortName;
    }

    /**
     * @param string $ourGameShortName
     */
    public function setOurGameShortName(string $ourGameShortName)
    {
        $this->ourGameShortName = $ourGameShortName;
    }

    /**
     * @return string
     */
    public function getOurGameWebsiteLink(): string
    {
        return $this->ourGameWebsiteLink;
    }

    /**
     * @param string $ourGameWebsiteLink
     */
    public function setOurGameWebsiteLink(string $ourGameWebsiteLink)
    {
        $this->ourGameWebsiteLink = $ourGameWebsiteLink;
    }

    /**
     * @return string
     */
    public function getOurGameFreeLink(): string
    {
        return $this->ourGameFreeLink;
    }

    /**
     * @param string $ourGameFreeLink
     */
    public function setOurGameFreeLink(string $ourGameFreeLink)
    {
        $this->ourGameFreeLink = $ourGameFreeLink;
    }

    /**
     * @return string
     */
    public function getOurGameAlpha(): string
    {
        return $this->ourGameAlpha;
    }

    /**
     * @param string $ourGameAlpha
     */
    public function setOurGameAlpha(string $ourGameAlpha)
    {
        $this->ourGameAlpha = $ourGameAlpha;
    }

    /**
     * @return string
     */
    public function getOurGameBeta(): string
    {
        return $this->ourGameBeta;
    }

    /**
     * @param string $ourGameBeta
     */
    public function setOurGameBeta(string $ourGameBeta)
    {
        $this->ourGameBeta = $ourGameBeta;
    }

    /**
     * @return string
     */
    public function getOurGameSourceCode(): string
    {
        return $this->ourGameSourceCode;
    }

    /**
     * @param string $ourGameSourceCode
     */
    public function setOurGameSourceCode(string $ourGameSourceCode)
    {
        $this->ourGameSourceCode = $ourGameSourceCode;
    }

    /**
     * @return string
     */
    public function getOurGameURLCDN(): string
    {
        return $this->ourGameURLCDN;
    }

    /**
     * @param string $ourGameURLCDN
     */
    public function setOurGameURLCDN(string $ourGameURLCDN)
    {
        $this->ourGameURLCDN = $ourGameURLCDN;
    }

    /**
     * @return string
     */
    public function getOurGameCover(): string
    {
        return $this->ourGameCover;
    }

    /**
     * @param string $ourGameCover
     */
    public function setOurGameCover(string $ourGameCover)
    {
        $this->ourGameCover = $ourGameCover;
    }

}
