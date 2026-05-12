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

use App\Repository\BugRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BugRepository::class)]
#[ORM\Table(name: '`bugs`')]
class Bug
{
    final public const STATUS_OPEN = 'open';
    final public const STATUS_IN_PROGRESS = 'in_progress';
    final public const STATUS_RESOLVED = 'resolved';
    final public const STATUS_CLOSED = 'closed';
    final public const STATUS_WONT_FIX = 'wont_fix';

    final public const SEVERITY_LOW = 'low';
    final public const SEVERITY_MEDIUM = 'medium';
    final public const SEVERITY_HIGH = 'high';
    final public const SEVERITY_CRITICAL = 'critical';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Bug title is required')]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => self::STATUS_OPEN])]
    #[Assert\Choice(choices: [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED, self::STATUS_WONT_FIX])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => self::SEVERITY_MEDIUM])]
    #[Assert\Choice(choices: [self::SEVERITY_LOW, self::SEVERITY_MEDIUM, self::SEVERITY_HIGH, self::SEVERITY_CRITICAL])]
    private string $severity = self::SEVERITY_MEDIUM;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reportedBugs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $reporter = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'assignedBugs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    #[ORM\ManyToOne(targetEntity: Card::class, inversedBy: 'bugs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Card $kanbanCard = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $reportedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reproduction_steps = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $expected_result = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $actual_result = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Choice(choices: ['Windows', 'Linux', 'macOS', 'Android', 'iOS', 'Other'], message: 'Choose a valid operating system.')]
    private ?string $operatingSystem = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Operating system version cannot be longer than {{ limit }} characters.')]
    private ?string $operatingSystemVersion = null;

    #[ORM\ManyToOne(targetEntity: OurGames::class, inversedBy: 'bugs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?OurGames $ourGame = null;

    #[ORM\ManyToOne(targetEntity: Mods::class, inversedBy: 'bugs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Mods $mod = null;

    public function __construct()
    {
        $this->reportedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;

        return $this;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): self
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getKanbanCard(): ?Card
    {
        return $this->kanbanCard;
    }

    public function setKanbanCard(?Card $kanbanCard): self
    {
        $this->kanbanCard = $kanbanCard;

        return $this;
    }

    // Aliases for backwards compatibility
    public function getCard(): ?Card
    {
        return $this->kanbanCard;
    }

    public function setCard(?Card $card): self
    {
        $this->kanbanCard = $card;

        return $this;
    }

    public function getReportedAt(): ?\DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function setReportedAt(\DateTimeImmutable $reportedAt): self
    {
        $this->reportedAt = $reportedAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getReproductionSteps(): ?string
    {
        return $this->reproduction_steps;
    }

    public function setReproductionSteps(?string $reproduction_steps): self
    {
        $this->reproduction_steps = $reproduction_steps;

        return $this;
    }

    public function getExpectedResult(): ?string
    {
        return $this->expected_result;
    }

    public function setExpectedResult(?string $expected_result): self
    {
        $this->expected_result = $expected_result;

        return $this;
    }

    public function getActualResult(): ?string
    {
        return $this->actual_result;
    }

    public function setActualResult(?string $actual_result): self
    {
        $this->actual_result = $actual_result;

        return $this;
    }

    public function getOperatingSystem(): ?string
    {
        return $this->operatingSystem;
    }

    public function setOperatingSystem(?string $operatingSystem): self
    {
        $this->operatingSystem = $operatingSystem;

        return $this;
    }

    public function getOperatingSystemVersion(): ?string
    {
        return $this->operatingSystemVersion;
    }

    public function setOperatingSystemVersion(?string $operatingSystemVersion): self
    {
        $this->operatingSystemVersion = $operatingSystemVersion;

        return $this;
    }

    public function getOurGame(): ?OurGames
    {
        return $this->ourGame;
    }

    public function setOurGame(?OurGames $ourGame): self
    {
        $this->ourGame = $ourGame;

        return $this;
    }

    public function getMod(): ?Mods
    {
        return $this->mod;
    }

    public function setMod(?Mods $mod): self
    {
        $this->mod = $mod;

        return $this;
    }
}
