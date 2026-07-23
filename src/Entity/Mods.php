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

use App\Repository\ModsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ModsRepository::class)]
#[ORM\Table(name: 'mods')]
#[ORM\HasLifecycleCallbacks]
class Mods
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $author = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $shortNameSlug = null;

    #[ORM\Column(type: Types::STRING, length: 5000)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON)]
    private array $compatible = [];

    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Assert\Url]
    private ?string $download = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $downloads = 0;

    #[Assert\Range(min: 1, max: 10)]
    #[ORM\Column(type: Types::FLOAT)]
    private float $rating = 1.0;

    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $bannerImage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $gallery = [];

    #[ORM\OneToMany(targetEntity: Board::class, mappedBy: 'mod')]
    private Collection $boards;

    #[ORM\OneToMany(targetEntity: Bug::class, mappedBy: 'mod')]
    private Collection $bugs;

    #[ORM\OneToMany(targetEntity: BugTracker::class, mappedBy: 'mod')]
    private Collection $bugTrackers;

    #[ORM\OneToMany(mappedBy: 'mod', targetEntity: ModRating::class, orphanRemoval: true)]
    private Collection $ratings;

    #[ORM\ManyToMany(targetEntity: ModCategory::class)]
    #[ORM\JoinTable(name: 'mods_categories')]
    private Collection $categories;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->boards = new ArrayCollection();
        $this->bugs = new ArrayCollection();
        $this->bugTrackers = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getShortNameSlug(): ?string
    {
        return $this->shortNameSlug;
    }

    public function setShortNameSlug(?string $shortNameSlug): void
    {
        $this->shortNameSlug = $shortNameSlug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCompatible(): array
    {
        return $this->compatible;
    }

    public function setCompatible(array $compatible): void
    {
        $this->compatible = $compatible;
    }

    public function getDownload(): ?string
    {
        return $this->download;
    }

    public function setDownload(?string $download): void
    {
        $this->download = $download;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function getDownloads(): int
    {
        return $this->downloads;
    }

    public function setDownloads(int $downloads): void
    {
        $this->downloads = $downloads;
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function setRating(float $rating): void
    {
        if ($rating < 1) {
            $rating = 1;
        }
        if ($rating > 10) {
            $rating = 10;
        }

        $this->rating = $rating;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getBannerImage(): ?string
    {
        return $this->bannerImage;
    }

    public function setBannerImage(?string $bannerImage): static
    {
        $this->bannerImage = $bannerImage;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getGallery(): array
    {
        return $this->gallery;
    }

    public function setGallery(array $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function addGalleryImage(string $image): static
    {
        if (!\in_array($image, $this->gallery, true)) {
            $this->gallery[] = $image;
        }

        return $this;
    }

    public function removeGalleryImage(string $image): static
    {
        $this->gallery = array_values(array_filter(
            $this->gallery,
            static fn ($item) => $item !== $image
        ));

        return $this;
    }

    public function getBoards(): Collection
    {
        return $this->boards;
    }

    public function addBoard(Board $board): self
    {
        if (!$this->boards->contains($board)) {
            $this->boards->add($board);
            $board->setMod($this);
        }

        return $this;
    }

    public function removeBoard(Board $board): self
    {
        if ($this->boards->removeElement($board)) {
            if ($board->getMod() === $this) {
                $board->setMod(null);
            }
        }

        return $this;
    }

    public function getBugTrackers(): Collection
    {
        return $this->bugTrackers;
    }

    public function addBugTracker(BugTracker $bugTracker): self
    {
        if (!$this->bugTrackers->contains($bugTracker)) {
            $this->bugTrackers->add($bugTracker);
            $bugTracker->setMod($this);
        }

        return $this;
    }

    public function removeBugTracker(BugTracker $bugTracker): self
    {
        if ($this->bugTrackers->removeElement($bugTracker)) {
            if ($bugTracker->getMod() === $this) {
                $bugTracker->setMod(null);
            }
        }

        return $this;
    }

    public function getBugs(): Collection
    {
        return $this->bugs;
    }

    public function addBug(Bug $bug): self
    {
        if (!$this->bugs->contains($bug)) {
            $this->bugs->add($bug);
            $bug->setMod($this);
        }

        return $this;
    }

    public function removeBug(Bug $bug): self
    {
        if ($this->bugs->removeElement($bug)) {
            if ($bug->getMod() === $this) {
                $bug->setMod(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ModRating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    /**
     * @return Collection<int, ModCategory>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(ModCategory $category): self
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(ModCategory $category): self
    {
        $this->categories->removeElement($category);

        return $this;
    }
}
