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

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $forum_signatures_enabled = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $forum_image_uploads_enabled = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $forum_polls_enabled = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private ?bool $forum_rich_embeds_enabled = true;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $forum_post_rate_limit = 10; // seconds

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

    public function isForumSignaturesEnabled(): ?bool
    {
        return $this->forum_signatures_enabled;
    }

    public function setForumSignaturesEnabled(bool $forum_signatures_enabled): self
    {
        $this->forum_signatures_enabled = $forum_signatures_enabled;

        return $this;
    }

    public function isForumImageUploadsEnabled(): ?bool
    {
        return $this->forum_image_uploads_enabled;
    }

    public function setForumImageUploadsEnabled(bool $forum_image_uploads_enabled): self
    {
        $this->forum_image_uploads_enabled = $forum_image_uploads_enabled;

        return $this;
    }

    public function isForumPollsEnabled(): ?bool
    {
        return $this->forum_polls_enabled;
    }

    public function setForumPollsEnabled(bool $forum_polls_enabled): self
    {
        $this->forum_polls_enabled = $forum_polls_enabled;

        return $this;
    }

    public function isForumRichEmbedsEnabled(): ?bool
    {
        return $this->forum_rich_embeds_enabled;
    }

    public function setForumRichEmbedsEnabled(bool $forum_rich_embeds_enabled): self
    {
        $this->forum_rich_embeds_enabled = $forum_rich_embeds_enabled;

        return $this;
    }

    public function getForumPostRateLimit(): ?int
    {
        return $this->forum_post_rate_limit;
    }

    public function setForumPostRateLimit(int $forum_post_rate_limit): self
    {
        $this->forum_post_rate_limit = $forum_post_rate_limit;

        return $this;
    }
}
