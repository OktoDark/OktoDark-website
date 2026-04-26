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

use App\Repository\ForumThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumThreadRepository::class)]
class ForumThread
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ForumCategory::class, inversedBy: 'threads')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumCategory $category;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private bool $locked = false;

    #[ORM\Column]
    private bool $pinned = false;

    #[ORM\Column]
    private int $views = 0;

    #[ORM\OneToMany(mappedBy: 'thread', targetEntity: ForumPost::class, cascade: ['remove'])]
    private Collection $posts;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    #[ORM\OneToMany(mappedBy: 'thread', targetEntity: ForumThreadRead::class, cascade: ['remove'])]
    private Collection $reads;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\ManyToMany(targetEntity: ForumTag::class, inversedBy: 'threads')]
    #[ORM\JoinTable(name: 'forum_thread_tags')]
    private Collection $tags;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'subscribedThreads')]
    private Collection $subscribers;

    #[ORM\OneToOne(mappedBy: 'thread', targetEntity: ForumPoll::class, cascade: ['persist', 'remove'])]
    private ?ForumPoll $poll = null;

    #[ORM\Column(length: 20)]
    private string $type = 'discussion'; // 'discussion', 'question', 'bug_report', 'feature_request'

    #[ORM\Column]
    private bool $isResolved = false;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->reads = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->subscribers = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ForumCategory
    {
        return $this->category;
    }

    public function setCategory(ForumCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function setPinned(bool $pinned): self
    {
        $this->pinned = $pinned;

        return $this;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;

        return $this;
    }

    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function getLastPost(): ?ForumPost
    {
        if ($this->posts->isEmpty()) {
            return null;
        }

        return $this->posts->last();
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getReads(): Collection
    {
        return $this->reads;
    }

    public function getReadTime(User $user): ?\DateTimeInterface
    {
        foreach ($this->reads as $read) {
            if ($read->getUser() === $user) {
                return $read->getLastReadAt();
            }
        }

        return null;
    }

    public function isUnreadFor(User $user): bool
    {
        $readTime = $this->getReadTime($user);

        if (null === $readTime) {
            return true; // never read
        }

        return $this->updatedAt > $readTime;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(ForumTag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(ForumTag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getSubscribers(): Collection
    {
        return $this->subscribers;
    }

    public function getPoll(): ?ForumPoll
    {
        return $this->poll;
    }

    public function setPoll(?ForumPoll $poll): self
    {
        if ($poll && $poll->getThread() !== $this) {
            $poll->setThread($this);
        }
        $this->poll = $poll;
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

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function setIsResolved(bool $isResolved): self
    {
        $this->isResolved = $isResolved;
        return $this;
    }
}
