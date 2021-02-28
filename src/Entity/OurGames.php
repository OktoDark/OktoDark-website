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
     * @Assert\Length(max="10")
     */
    private $ourGamePlayOnlineID;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="100")
     */
    private $ourGamePlayOnline;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="500")
     */
    private $ourGamePlayOnlineText;

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

    public function getOurGameName(): string
    {
        return $this->ourGameName;
    }

    public function setOurGameName(string $ourGameName)
    {
        $this->ourGameName = $ourGameName;
    }

    public function getOurGameShortName(): string
    {
        return $this->ourGameShortName;
    }

    public function setOurGameShortName(string $ourGameShortName)
    {
        $this->ourGameShortName = $ourGameShortName;
    }

    public function getOurGameWebsiteLink(): string
    {
        return $this->ourGameWebsiteLink;
    }

    public function setOurGameWebsiteLink(string $ourGameWebsiteLink)
    {
        $this->ourGameWebsiteLink = $ourGameWebsiteLink;
    }

    public function getOurGameFreeLink(): string
    {
        return $this->ourGameFreeLink;
    }

    public function setOurGameFreeLink(string $ourGameFreeLink)
    {
        $this->ourGameFreeLink = $ourGameFreeLink;
    }

    public function getOurGamePlayOnlineID(): string
    {
        return $this->ourGamePlayOnlineID;
    }

    public function setOurGamePlayOnlineID(string $ourGamePlayOnlineID): void
    {
        $this->ourGamePlayOnlineID = $ourGamePlayOnlineID;
    }

    public function getOurGamePlayOnline(): string
    {
        return $this->ourGamePlayOnline;
    }

    public function setOurGamePlayOnline(string $ourGamePlayOnline): void
    {
        $this->ourGamePlayOnline = $ourGamePlayOnline;
    }

    public function getOurGamePlayOnlineText(): string
    {
        return $this->ourGamePlayOnlineText;
    }

    public function setOurGamePlayOnlineText(string $ourGamePlayOnlineText): void
    {
        $this->ourGamePlayOnlineText = $ourGamePlayOnlineText;
    }

    public function getOurGameAlpha(): string
    {
        return $this->ourGameAlpha;
    }

    public function setOurGameAlpha(string $ourGameAlpha)
    {
        $this->ourGameAlpha = $ourGameAlpha;
    }

    public function getOurGameBeta(): string
    {
        return $this->ourGameBeta;
    }

    public function setOurGameBeta(string $ourGameBeta)
    {
        $this->ourGameBeta = $ourGameBeta;
    }

    public function getOurGameSourceCode(): string
    {
        return $this->ourGameSourceCode;
    }

    public function setOurGameSourceCode(string $ourGameSourceCode)
    {
        $this->ourGameSourceCode = $ourGameSourceCode;
    }

    public function getOurGameURLCDN(): string
    {
        return $this->ourGameURLCDN;
    }

    public function setOurGameURLCDN(string $ourGameURLCDN)
    {
        $this->ourGameURLCDN = $ourGameURLCDN;
    }

    public function getOurGameCover(): string
    {
        return $this->ourGameCover;
    }

    public function setOurGameCover(string $ourGameCover)
    {
        $this->ourGameCover = $ourGameCover;
    }
}
