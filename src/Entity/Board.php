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

use App\Repository\BoardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BoardRepository::class)]
#[ORM\Table(name: '`boards`')]
class Board
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Board title is required')]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true, options: ['default' => '#1E90FF'])]
    private ?string $backgroundColor = '#1E90FF';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPublic = false;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedBoards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: BoardColumn::class, mappedBy: 'board', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $columns;

    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'board', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $cards;

    #[ORM\OneToMany(targetEntity: CardLabel::class, mappedBy: 'board', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $labels;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'memberBoards')]
    #[ORM\JoinTable(name: 'board_members')]
    #[ORM\JoinColumn(name: 'board_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $members;

    #[ORM\ManyToOne(targetEntity: OurGames::class, inversedBy: 'boards')]
    #[ORM\JoinColumn(nullable: true)]
    private ?OurGames $ourGame = null;

    #[ORM\ManyToOne(targetEntity: Mods::class, inversedBy: 'boards')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Mods $mod = null;

    public function __construct()
    {
        $this->columns = new ArrayCollection();
        $this->cards = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->members = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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
        if (empty($this->slug)) {
            $this->slug = mb_strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
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

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): self
    {
        $this->backgroundColor = $backgroundColor;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

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

    /**
     * @return Collection<int, BoardColumn>
     */
    public function getColumns(): Collection
    {
        return $this->columns;
    }

    public function addColumn(BoardColumn $column): self
    {
        if (!$this->columns->contains($column)) {
            $this->columns->add($column);
            $column->setBoard($this);
        }

        return $this;
    }

    public function removeColumn(BoardColumn $column): self
    {
        if ($this->columns->removeElement($column)) {
            if ($column->getBoard() === $this) {
                $column->setBoard(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Card>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(Card $card): self
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setBoard($this);
        }

        return $this;
    }

    public function removeCard(Card $card): self
    {
        if ($this->cards->removeElement($card)) {
            if ($card->getBoard() === $this) {
                $card->setBoard(null);
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
            $label->setBoard($this);
        }

        return $this;
    }

    public function removeLabel(CardLabel $label): self
    {
        if ($this->labels->removeElement($label)) {
            if ($label->getBoard() === $this) {
                $label->setBoard(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): self
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
        }

        return $this;
    }

    public function removeMember(User $member): self
    {
        $this->members->removeElement($member);

        return $this;
    }

    public function isMember(User $user): bool
    {
        return $this->members->contains($user) || $this->owner === $user;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
