<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SettingsRepository")
 * @ORM\Table(name="settings")
 */
class Settings
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="10")
     */
    private $siteName;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Assert\Length(max="50")
     */
    private $jobmail;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="50")
     */
    private $siteCDN;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     * @Assert\Length(max="10")
     */
    private $logoName;

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
