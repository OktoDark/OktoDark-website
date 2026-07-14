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

use App\Repository\EpisodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EpisodeRepository::class)]
#[ORM\Table(name: 'tracking_episode')]
class Episode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MediaMetadata::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MediaMetadata $mediaMetadata = null;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'episodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Season $relatedSeason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ─────────────────────────────────────────────
    // CORE GETTERS / SETTERS
    // ─────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMediaMetadata(): ?MediaMetadata
    {
        return $this->mediaMetadata;
    }

    public function setMediaMetadata(?MediaMetadata $mediaMetadata): self
    {
        $this->mediaMetadata = $mediaMetadata;

        return $this;
    }

    public function getRelatedSeason(): ?Season
    {
        return $this->relatedSeason;
    }

    public function setRelatedSeason(?Season $relatedSeason): self
    {
        $this->relatedSeason = $relatedSeason;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    // ─────────────────────────────────────────────
    // WATCH STATUS
    // ─────────────────────────────────────────────

    public function isWatched(): bool
    {
        return null !== $this->endDate;
    }

    // ─────────────────────────────────────────────
    // PASSTHROUGH HELPERS FOR METADATA FIELDS
    // ─────────────────────────────────────────────

    public function getEpisodeNumber(): ?int
    {
        return $this->mediaMetadata?->getEpisodeNumber();
    }

    public function getSeasonNumber(): ?int
    {
        return $this->relatedSeason?->getSeasonNumber();
    }

    public function getTitle(): ?string
    {
        return $this->mediaMetadata?->getTitle();
    }

    public function getImage(): ?string
    {
        return $this->mediaMetadata?->getImage();
    }

    public function getScreenshot(): ?string
    {
        return $this->mediaMetadata?->getScreenshot();
    }

    /**
     * @return array<int, array{name: ?string, character: ?string, image: ?string}>|null
     */
    public function getCast(): ?array
    {
        return $this->mediaMetadata?->getCast();
    }

    public function getTrailer(): ?string
    {
        return $this->mediaMetadata?->getTrailer();
    }

    public function getOverview(): ?string
    {
        return $this->mediaMetadata?->getOverview();
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->mediaMetadata?->getReleaseDate();
    }

    // ─────────────────────────────────────────────
    // FORMATTED OUTPUT
    // ─────────────────────────────────────────────

    public function getFormattedEpisode(): string
    {
        return \sprintf(
            'S%02dE%02d',
            $this->getSeasonNumber() ?? 0,
            $this->getEpisodeNumber() ?? 0
        );
    }
}
