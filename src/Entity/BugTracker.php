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

use App\Repository\BugTrackerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BugTrackerRepository::class)]
#[ORM\Table(name: 'bug_trackers')]
class BugTracker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Bug tracker name is required')]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Slug is required')]
    #[Assert\Length(min: 1, max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/', message: 'Slug can only contain letters, numbers, dashes, and underscores.')]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedBugTrackers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: OurGames::class, inversedBy: 'bugTrackers')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?OurGames $ourGame = null;

    #[ORM\ManyToOne(targetEntity: Mods::class, inversedBy: 'bugTrackers')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Mods $mod = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'trackedBugTrackers')]
    #[ORM\JoinTable(name: 'bug_tracker_trackers')]
    #[ORM\JoinColumn(name: 'bug_tracker_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $trackers;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->trackers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

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

    /**
     * @return Collection<int, User>
     */
    public function getTrackers(): Collection
    {
        return $this->trackers;
    }

    public function addTracker(User $tracker): self
    {
        if (!$this->trackers->contains($tracker)) {
            $this->trackers->add($tracker);
        }

        return $this;
    }

    public function removeTracker(User $tracker): self
    {
        $this->trackers->removeElement($tracker);

        return $this;
    }

    public function isTracker(User $user): bool
    {
        return $this->trackers->contains($user);
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
}
