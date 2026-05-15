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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $shortNameSlug = null; // New field for slug

    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $websiteLink = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $freeLink = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $playOnlineID = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $playOnline = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $playOnlineText = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $alpha = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $beta = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $sourceCode = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $urlCDN = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $cover = null;

    // Changed to unidirectional OneToOne to resolve mapping conflict
    #[ORM\OneToOne(targetEntity: Board::class)]
    #[ORM\JoinColumn(name: 'bug_board_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Board $bugLink = null;

    #[ORM\OneToMany(targetEntity: Board::class, mappedBy: 'ourGame')]
    private Collection $boards;

    #[ORM\OneToMany(targetEntity: Bug::class, mappedBy: 'ourGame')]
    private Collection $bugs;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $systemRequirements = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private ?bool $stable = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private ?bool $isAlpha = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private ?bool $isBeta = false;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $stableLink = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $images = null; // Stores JSON array of image filenames

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $videos = null; // Stores JSON array of video filenames

    public function __construct()
    {
        $this->boards = new ArrayCollection();
        $this->bugs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getShortNameSlug(): ?string
    {
        return $this->shortNameSlug;
    }

    public function setShortNameSlug(string $shortNameSlug): self
    {
        $this->shortNameSlug = $shortNameSlug;

        return $this;
    }

    public function getWebsiteLink(): ?string
    {
        return $this->websiteLink;
    }

    public function setWebsiteLink(string $websiteLink): void
    {
        $this->websiteLink = $websiteLink;
    }

    public function getFreeLink(): ?string
    {
        return $this->freeLink;
    }

    public function setFreeLink(?string $freeLink): self
    {
        $this->freeLink = $freeLink;

        return $this;
    }

    public function getPlayOnlineID(): ?string
    {
        return $this->playOnlineID;
    }

    public function setPlayOnlineID(?string $playOnlineID): self
    {
        $this->playOnlineID = $playOnlineID;

        return $this;
    }

    public function getPlayOnline(): ?string
    {
        return $this->playOnline;
    }

    public function setPlayOnline(?string $playOnline): self
    {
        $this->playOnline = $playOnline;

        return $this;
    }

    public function getPlayOnlineText(): ?string
    {
        return $this->playOnlineText;
    }

    public function setPlayOnlineText(?string $playOnlineText): self
    {
        $this->playOnlineText = $playOnlineText;

        return $this;
    }

    public function getAlpha(): ?string
    {
        return $this->alpha;
    }

    public function setAlpha(?string $alpha): self
    {
        $this->alpha = $alpha;

        return $this;
    }

    public function getBeta(): ?string
    {
        return $this->beta;
    }

    public function setBeta(?string $beta): self
    {
        $this->beta = $beta;

        return $this;
    }

    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }

    public function setSourceCode(?string $sourceCode): self
    {
        $this->sourceCode = $sourceCode;

        return $this;
    }

    public function getUrlCDN(): ?string
    {
        return $this->urlCDN;
    }

    public function setUrlCDN(?string $urlCDN): self
    {
        $this->urlCDN = $urlCDN;

        return $this;
    }

    public function getCover(): ?string
    {
        return $this->cover;
    }

    public function setCover(?string $cover): self
    {
        $this->cover = $cover;

        return $this;
    }

    public function getBugLink(): ?Board
    {
        return $this->bugLink;
    }

    public function setBugLink(?Board $bugLink): self
    {
        $this->bugLink = $bugLink;

        return $this;
    }

    /**
     * @return Collection<int, Board>
     */
    public function getBoards(): Collection
    {
        return $this->boards;
    }

    public function addBoard(Board $board): self
    {
        if (!$this->boards->contains($board)) {
            $this->boards->add($board);
            $board->setOurGame($this);
        }

        return $this;
    }

    public function removeBoard(Board $board): self
    {
        if ($this->boards->removeElement($board)) {
            // set the owning side to null (unless already changed)
            if ($board->getOurGame() === $this) {
                $board->setOurGame(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Bug>
     */
    public function getBugs(): Collection
    {
        return $this->bugs;
    }

    public function addBug(Bug $bug): self
    {
        if (!$this->bugs->contains($bug)) {
            $this->bugs->add($bug);
            $bug->setOurGame($this);
        }

        return $this;
    }

    public function removeBug(Bug $bug): self
    {
        if ($this->bugs->removeElement($bug)) {
            // set the owning side to null (unless already changed)
            if ($bug->getOurGame() === $this) {
                $bug->setOurGame(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSystemRequirements(): ?string
    {
        return $this->systemRequirements;
    }

    public function setSystemRequirements(?string $systemRequirements): self
    {
        $this->systemRequirements = $systemRequirements;

        return $this;
    }

    public function isStable(): ?bool
    {
        return $this->stable;
    }

    public function setStable(bool $stable): self
    {
        $this->stable = $stable;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function isIsAlpha(): ?bool
    {
        return $this->isAlpha;
    }

    public function setIsAlpha(bool $isAlpha): self
    {
        $this->isAlpha = $isAlpha;

        return $this;
    }

    public function isIsBeta(): ?bool
    {
        return $this->isBeta;
    }

    public function setIsBeta(bool $isBeta): self
    {
        $this->isBeta = $isBeta;

        return $this;
    }

    public function getStableLink(): ?string
    {
        return $this->stableLink;
    }

    public function setStableLink(?string $stableLink): self
    {
        $this->stableLink = $stableLink;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getImages(): array
    {
        return json_decode($this->images ?? '[]', true);
    }

    /**
     * @param array<string> $images
     */
    public function setImages(array $images): self
    {
        $this->images = json_encode($images);

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getVideos(): array
    {
        return json_decode($this->videos ?? '[]', true);
    }

    /**
     * @param array<string> $videos
     */
    public function setVideos(array $videos): self
    {
        $this->videos = json_encode($videos);

        return $this;
    }
}
