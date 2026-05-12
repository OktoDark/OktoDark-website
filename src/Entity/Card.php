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

use App\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\Table(name: '`cards`')]
class Card
{
    final public const TYPE_TASK = 'task';
    final public const TYPE_BUG = 'bug';
    final public const TYPE_FEATURE = 'feature';

    final public const PRIORITY_LOW = 'low';
    final public const PRIORITY_MEDIUM = 'medium';
    final public const PRIORITY_HIGH = 'high';
    final public const PRIORITY_CRITICAL = 'critical';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Board::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Board $board = null;

    #[ORM\ManyToOne(targetEntity: BoardColumn::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BoardColumn $column = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Card title is required')]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::TYPE_TASK])]
    #[Assert\Choice(choices: [self::TYPE_TASK, self::TYPE_BUG, self::TYPE_FEATURE])]
    private string $type = self::TYPE_TASK;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::PRIORITY_MEDIUM])]
    #[Assert\Choice(choices: [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_CRITICAL])]
    private string $priority = self::PRIORITY_MEDIUM;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\OneToMany(targetEntity: CardAssignee::class, mappedBy: 'card', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $assignees;

    #[ORM\ManyToMany(targetEntity: CardLabel::class)]
    #[ORM\JoinTable(name: 'card_labels')]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'label_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $labels;

    #[ORM\OneToMany(targetEntity: CardComment::class, mappedBy: 'card', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: Bug::class, mappedBy: 'kanbanCard', cascade: ['persist'], orphanRemoval: false)]
    private Collection $bugs;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'createdCards')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->assignees = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->bugs = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBoard(): ?Board
    {
        return $this->board;
    }

    public function setBoard(?Board $board): self
    {
        $this->board = $board;

        return $this;
    }

    public function getColumn(): ?BoardColumn
    {
        return $this->column;
    }

    public function setColumn(?BoardColumn $column): self
    {
        $this->column = $column;

        return $this;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    /**
     * @return Collection<int, CardAssignee>
     */
    public function getAssignees(): Collection
    {
        return $this->assignees;
    }

    public function addAssignee(CardAssignee $assignee): self
    {
        if (!$this->assignees->contains($assignee)) {
            $this->assignees->add($assignee);
            $assignee->setCard($this);
        }

        return $this;
    }

    public function removeAssignee(CardAssignee $assignee): self
    {
        if ($this->assignees->removeElement($assignee)) {
            if ($assignee->getCard() === $this) {
                $assignee->setCard(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CardLabel>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(CardLabel $label): self
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }

        return $this;
    }

    public function removeLabel(CardLabel $label): self
    {
        $this->labels->removeElement($label);

        return $this;
    }

    /**
     * @return Collection<int, CardComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(CardComment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setCard($this);
        }

        return $this;
    }

    public function removeComment(CardComment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getCard() === $this) {
                $comment->setCard(null);
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
            $bug->setKanbanCard($this);
        }

        return $this;
    }

    public function removeBug(Bug $bug): self
    {
        if ($this->bugs->removeElement($bug)) {
            if ($bug->getKanbanCard() === $this) {
                $bug->setKanbanCard(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
