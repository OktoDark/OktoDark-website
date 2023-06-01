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

use App\Repository\OurGamesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Here will be all for games, downloading links for alpha/beta stages for our members.
 * Needed to be reworked all database for a clean sheets.
 */
#[ORM\Entity(repositoryClass: OurGamesRepository::class)]
#[ORM\Table(name: 'our_games')]
class OurGames
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameName = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameShortName = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameWebsiteLink = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameFreeLink = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 10)]
    private ?string $ourGamePlayOnlineID = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGamePlayOnline = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 500)]
    private ?string $ourGamePlayOnlineText = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameAlpha = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameBeta = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameSourceCode = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameURLCDN = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 100)]
    private ?string $ourGameCover = null;

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
