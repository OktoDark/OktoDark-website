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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MediaMetadata::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?MediaMetadata $mediaMetadata = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 1, nullable: true)]
    protected ?string $score = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0, 'unsigned' => true])]
    protected int $progress = 0;

    #[ORM\Column(length: 20, enumType: WatchStatus::class)]
    protected ?WatchStatus $status = WatchStatus::COMPLETED;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $notes = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?\DateTimeImmutable $progressedAt = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(?string $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): self
    {
        $this->progress = $progress;

        return $this;
    }

    public function getStatus(): ?WatchStatus
    {
        return $this->status;
    }

    public function setStatus(WatchStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProgressedAt(): ?\DateTimeImmutable
    {
        return $this->progressedAt;
    }

    public function setProgressedAt(?\DateTimeImmutable $progressedAt): self
    {
        $this->progressedAt = $progressedAt;

        return $this;
    }

    // ─────────────────────────────────────────────
    // METADATA PASSTHROUGH HELPERS
    // ─────────────────────────────────────────────

    public function getMediaId(): ?string
    {
        return $this->mediaMetadata?->getMediaId();
    }

    public function getTitle(): ?string
    {
        return $this->mediaMetadata?->getTitle();
    }

    public function getOriginalTitle(): ?string
    {
        return $this->mediaMetadata?->getOriginalTitle();
    }

    public function getCoverUrl(): ?string
    {
        return $this->mediaMetadata?->getImage();
    }

    public function getOverview(): ?string
    {
        return $this->mediaMetadata?->getOverview();
    }

    public function getGenres(): ?array
    {
        return $this->mediaMetadata?->getGenres();
    }

    public function getCast(): ?array
    {
        return $this->mediaMetadata?->getCast();
    }

    public function getTrailer(): ?string
    {
        return $this->mediaMetadata?->getTrailer();
    }

    public function getRuntime(): ?int
    {
        return $this->mediaMetadata?->getRuntime();
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->mediaMetadata?->getReleaseDate();
    }

    public function getFormattedTitle(): string
    {
        return $this->getTitle() ?? 'Untitled';
    }

    // ─────────────────────────────────────────────
    // FORMATTED OUTPUT HELPERS
    // ─────────────────────────────────────────────

    public function getFormattedScore(): string
    {
        if (null === $this->score || '' === $this->score) {
            return '—';
        }

        $scoreFloat = (float) $this->score;

        if (10.0 === $scoreFloat || 0.0 === $scoreFloat) {
            return (string) (int) $scoreFloat;
        }

        return number_format($scoreFloat, 1);
    }

    public function getFormattedProgress(): string
    {
        return (string) $this->progress;
    }

    // ─────────────────────────────────────────────
    // STATUS HELPERS
    // ─────────────────────────────────────────────

    public function isTracking(): bool
    {
        return WatchStatus::PLANNING !== $this->status;
    }

    public function isCompleted(): bool
    {
        return WatchStatus::COMPLETED === $this->status;
    }

    public function isInProgress(): bool
    {
        return WatchStatus::IN_PROGRESS === $this->status;
    }

    public function getTrackingDuration(): ?\DateInterval
    {
        if (null === $this->startDate || null === $this->endDate) {
            return null;
        }

        return $this->endDate->diff($this->startDate);
    }

    public function getStatusLabel(): string
    {
        return $this->status?->value ?? '';
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            WatchStatus::COMPLETED => 'bg-success',
            WatchStatus::IN_PROGRESS => 'bg-primary',
            WatchStatus::PLANNING => 'bg-info',
            WatchStatus::PAUSED => 'bg-warning',
            WatchStatus::DROPPED => 'bg-danger',
            default => 'bg-secondary',
        };
    }
}
