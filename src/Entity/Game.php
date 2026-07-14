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

use App\Repository\GameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'tracking_game')]
class Game extends AbstractMedia
{
    /**
     * Replicates the custom game progress formatting logic from Yamtrack.
     * Converts raw progress minutes into an 'HH:MM' string structure.
     */
    public function getFormattedProgress(): string
    {
        $hours = floor($this->progress / 60);
        $minutes = $this->progress % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
