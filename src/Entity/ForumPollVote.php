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

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'forum_poll_vote')]
#[ORM\UniqueConstraint(name: 'user_poll_unique', columns: ['user_id', 'poll_id'])]
class ForumPollVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: ForumPoll::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ForumPoll $poll;

    #[ORM\ManyToOne(targetEntity: ForumPollOption::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumPollOption $option;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getOption(): ForumPollOption
    {
        return $this->option;
    }

    public function setOption(ForumPollOption $option): self
    {
        $this->option = $option;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
