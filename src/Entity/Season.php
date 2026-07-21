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

use App\Enum\WatchStatus;
use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\Table(name: 'tracking_season')]
#[ORM\UniqueConstraint(name: 'unique_season_user_meta', columns: ['user_id', 'media_metadata_id'])]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 1, nullable: true)]
    private ?string $score = null;

    #[ORM\Column(type: 'integer')]
    private int $progress = 0;

    #[ORM\Column(type: 'string', enumType: WatchStatus::class)]
    private WatchStatus $status = WatchStatus::PLANNING;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $progressedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: MediaMetadata::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?MediaMetadata $mediaMetadata = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: TV::class, inversedBy: 'seasons')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?TV $relatedTv = null;

    /**
     * Episodes belonging to this season.
     *
     * @var Collection<int, Episode>
     */
    #[ORM\OneToMany(mappedBy: 'relatedSeason', targetEntity: Episode::class)]
    private Collection $episodes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->episodes = new ArrayCollection();
    }

    // ─────────────────────────────────────────────
    // PASSTHROUGH HELPERS FOR METADATA FIELDS
    // ─────────────────────────────────────────────

    public function getSeasonNumber(): ?int
    {
        return $this->mediaMetadata?->getSeasonNumber();
    }

    public function getEpisodeCount(): int
    {
        return $this->episodes->count();
    }

    public function getTotalEpisodes(): int
    {
        return $this->episodes->count();
    }

    // ─────────────────────────────────────────────
    // GETTERS / SETTERS
    // ─────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(?string $score): void
    {
        $this->score = $score;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): void
    {
        $this->progress = $progress;
    }

    public function getStatus(): WatchStatus
    {
        return $this->status;
    }

    public function setStatus(WatchStatus $status): void
    {
        $this->status = $status;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getProgressedAt(): ?\DateTimeInterface
    {
        return $this->progressedAt;
    }

    public function setProgressedAt(?\DateTimeInterface $progressedAt): void
    {
        $this->progressedAt = $progressedAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getMediaMetadata(): ?MediaMetadata
    {
        return $this->mediaMetadata;
    }

    public function setMediaMetadata(?MediaMetadata $mediaMetadata): void
    {
        $this->mediaMetadata = $mediaMetadata;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getRelatedTv(): ?TV
    {
        return $this->relatedTv;
    }

    public function setRelatedTv(?TV $relatedTv): void
    {
        $this->relatedTv = $relatedTv;
    }

    /**
     * @return Collection<int, Episode>
     */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function setEpisodes(iterable $episodes): void
    {
        $this->episodes = new ArrayCollection();

        foreach ($episodes as $ep) {
            $this->episodes->add($ep);
        }
    }
}
