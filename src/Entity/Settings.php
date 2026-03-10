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
    private ?string $jobmail = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $siteCDN = null;

    #[ORM\Column(length: 50)]
    private ?string $theme = 'grey';

    #[ORM\Column(type: Types::STRING, length: 10)]
    private ?string $logoName = null;

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

    public function getJobmail(): ?string
    {
        return $this->jobmail;
    }

    public function setJobmail(string $jobmail): void
    {
        $this->jobmail = $jobmail;
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
