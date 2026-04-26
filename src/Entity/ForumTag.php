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

use App\Repository\ForumTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumTagRepository::class)]
class ForumTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $name;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null; // Neon style hex color

    #[ORM\ManyToMany(targetEntity: ForumThread::class, mappedBy: 'tags')]
    private Collection $threads;

    public function __construct()
    {
        $this->threads = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function getThreads(): Collection
    {
        return $this->threads;
    }

    public function addThread(ForumThread $thread): self
    {
        if (!$this->threads->contains($thread)) {
            $this->threads->add($thread);
            $thread->addTag($this);
        }

        return $this;
    }

    public function removeThread(ForumThread $thread): self
    {
        if ($this->threads->removeElement($thread)) {
            $thread->removeTag($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
