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

use App\Repository\ForumThreadReadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumThreadReadRepository::class)]
#[ORM\Table(name: 'forum_thread_read')]
class ForumThreadRead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ForumThread::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ForumThread $thread = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $lastReadAt;

    public function __construct()
    {
        $this->lastReadAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getThread(): ?ForumThread
    {
        return $this->thread;
    }

    public function setThread(ForumThread $thread): self
    {
        $this->thread = $thread;
        return $this;
    }

    public function getLastReadAt(): \DateTimeInterface
    {
        return $this->lastReadAt;
    }

    public function setLastReadAt(\DateTimeInterface $lastReadAt): self
    {
        $this->lastReadAt = $lastReadAt;
        return $this;
    }
}