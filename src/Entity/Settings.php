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

use App\Repository\SettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'settings')]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private ?string $siteName = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $jobMail = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $siteCDN = null;

    #[ORM\Column(length: 50)]
    private ?string $theme = 'modern';

    #[ORM\Column(type: Types::STRING, length: 10)]
    private ?string $logoName = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $register_enabled = true;

    public function getRegisterEnabled(): ?bool
    {
        return $this->register_enabled;
    }

    public function setRegisterEnabled(bool $enabled): self
    {
        $this->register_enabled = $enabled;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteName(): ?string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): void
    {
        $this->siteName = $siteName;
    }

    public function getJobMail(): ?string
    {
        return $this->jobMail;
    }

    public function setJobMail(string $jobMail): void
    {
        $this->jobMail = $jobMail;
    }

    public function getSiteCDN(): ?string
    {
        return $this->siteCDN;
    }

    public function setSiteCDN(string $siteCDN): void
    {
        $this->siteCDN = $siteCDN;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getLogoName(): ?string
    {
        return $this->logoName;
    }

    public function setLogoName(string $logoName): void
    {
        $this->logoName = $logoName;
    }
}
