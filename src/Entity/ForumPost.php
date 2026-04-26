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

use App\Repository\ForumPostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
class ForumPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ForumThread::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumThread $thread;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $editedBy = null;

    #[ORM\Column(type: 'boolean')]
    private bool $reported = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isAcceptedAnswer = false;

    #[ORM\OneToMany(mappedBy: 'post', targetEntity: ForumReaction::class, cascade: ['remove'])]
    private Collection $reactions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->reactions = new ArrayCollection();
    }

    public function isReported(): bool
    {
        return $this->reported;
    }

    public function setReported(bool $reported): void
    {
        $this->reported = $reported;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getThread(): ForumThread
    {
        return $this->thread;
    }

    public function setThread(ForumThread $thread): self
    {
        $this->thread = $thread;

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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

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

    public function getEditedBy(): ?User
    {
        return $this->editedBy;
    }

    public function setEditedBy(?User $editedBy): self
    {
        $this->editedBy = $editedBy;

        return $this;
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

    public function isAcceptedAnswer(): bool
    {
        return $this->isAcceptedAnswer;
    }

    public function setIsAcceptedAnswer(bool $isAcceptedAnswer): self
    {
        $this->isAcceptedAnswer = $isAcceptedAnswer;

        return $this;
    }

    /**
     * @return Collection<int, ForumReaction>
     */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }

    public function getUpvotesCount(): int
    {
        return $this->reactions->filter(static fn ($r) => 'upvote' === $r->getType())->count();
    }

    public function getDownvotesCount(): int
    {
        return $this->reactions->filter(static fn ($r) => 'downvote' === $r->getType())->count();
    }

    public function getScore(): int
    {
        return $this->getUpvotesCount() - $this->getDownvotesCount();
    }

    public function getUserReaction(User $user): ?string
    {
        foreach ($this->reactions as $reaction) {
            if ($reaction->getUser() === $user) {
                return $reaction->getType();
            }
        }

        return null;
    }
}
