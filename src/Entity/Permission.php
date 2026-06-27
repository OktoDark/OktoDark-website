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

use App\Repository\PermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'permission')]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $name; // e.g. "mod.upload"

    #[ORM\Column(type: 'string', length: 150)]
    private string $label; // e.g. "Upload mods"

    #[ORM\Column(type: 'string', length: 50)]
    private string $area; // e.g. "mods", "forum", "admin"

    #[ORM\ManyToOne(targetEntity: PermissionGroup::class)]
    private ?PermissionGroup $group = null;

    #[ORM\OneToMany(targetEntity: RolePermission::class, mappedBy: 'permission', cascade: ['remove'])]
    private Collection $rolePermissions;

    public function __construct()
    {
        $this->rolePermissions = new ArrayCollection();
    }

    // ─────────────────────────────────────────────
    // BASIC FIELDS
    // ─────────────────────────────────────────────

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    public function setArea(string $area): self
    {
        $this->area = $area;

        return $this;
    }

    // ─────────────────────────────────────────────
    // GROUP
    // ─────────────────────────────────────────────

    public function getGroup(): ?PermissionGroup
    {
        return $this->group;
    }

    public function setGroup(?PermissionGroup $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getGroupName(): string
    {
        return $this->group?->getName() ?? 'General';
    }

    public function getGroupLabel(): string
    {
        return $this->group?->getLabel() ?? 'General';
    }

    // ─────────────────────────────────────────────
    // ROLE PERMISSIONS (INVERSE SIDE)
    // ─────────────────────────────────────────────

    /**
     * @return Collection<int, RolePermission>
     */
    public function getRolePermissions(): Collection
    {
        return $this->rolePermissions;
    }

    public function addRolePermission(RolePermission $rp): self
    {
        if (!$this->rolePermissions->contains($rp)) {
            $this->rolePermissions->add($rp);
            $rp->setPermission($this);
        }

        return $this;
    }

    public function removeRolePermission(RolePermission $rp): self
    {
        if ($this->rolePermissions->removeElement($rp)) {
            if ($rp->getPermission() === $this) {
                $rp->setPermission(null);
            }
        }

        return $this;
    }
}
