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

use App\Repository\ForumPollRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumPollRepository::class)]
class ForumPoll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: ForumThread::class, inversedBy: 'poll')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumThread $thread;

    #[ORM\Column(length: 255)]
    private string $question;

    #[ORM\OneToMany(mappedBy: 'poll', targetEntity: ForumPollOption::class, cascade: ['persist', 'remove'])]
    private Collection $options;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct()
    {
        $this->options = new ArrayCollection();
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

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @return Collection<int, ForumPollOption>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(ForumPollOption $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setPoll($this);
        }
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function hasExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }
        return $this->expiresAt < new \DateTime();
    }
}
