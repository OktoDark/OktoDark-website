<?php

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

    /**
     * @return mixed
     */
    public function getLogoName()
    {
        return $this->logoName;
    }

    /**
     * @param mixed $logoName
     */
    public function setLogoName($logoName)
    {
        $this->logoName = $logoName;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getSiteName()
    {
        return $this->siteName;
    }

    /**
     * @param mixed $siteName
     */
    public function setSiteName($siteName)
    {
        $this->siteName = $siteName;
    }

    /**
     * @return mixed
     */
    public function getSiteCDN()
    {
        return $this->siteCDN;
    }

    /**
     * @param mixed $siteCDN
     */
    public function setSiteCDN($siteCDN)
    {
        $this->siteCDN = $siteCDN;
    }

}
