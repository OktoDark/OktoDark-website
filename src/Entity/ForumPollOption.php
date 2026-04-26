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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ForumPollOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ForumPoll::class, inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumPoll $poll;

    #[ORM\Column(length: 255)]
    private string $text;

    #[ORM\OneToMany(targetEntity: ForumPollVote::class, mappedBy: 'option', cascade: ['remove'])]
    private Collection $votes;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPoll(): ForumPoll
    {
        return $this->poll;
    }

    public function setPoll(ForumPoll $poll): self
    {
        $this->poll = $poll;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return Collection<int, ForumPollVote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }
}
