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
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'settings')]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 10)]
    private ?string $siteName = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 50)]
    private ?string $jobmail = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 50)]
    private ?string $siteCDN = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 10)]
    private ?string $logoName = null;

    public function getLogoName()
    {
        return $this->logoName;
    }

    public function setLogoName($logoName)
    {
        $this->logoName = $logoName;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getSiteName()
    {
        return $this->siteName;
    }

    public function setSiteName($siteName)
    {
        $this->siteName = $siteName;
    }

    public function getJobmail()
    {
        return $this->jobmail;
    }

    public function setJobmail($jobmail)
    {
        $this->jobmail = $jobmail;
    }

    public function getSiteCDN()
    {
        return $this->siteCDN;
    }

    public function setSiteCDN($siteCDN)
    {
        $this->siteCDN = $siteCDN;
    }
}
