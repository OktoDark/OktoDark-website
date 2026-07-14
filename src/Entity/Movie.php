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

use App\Repository\MovieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
#[ORM\Table(name: 'tracking_movie')]
class Movie extends AbstractMedia
{
    public function getCoverUrl(): ?string
    {
        $meta = $this->mediaMetadata;

        if (!$meta) {
            return null;
        }

        return $meta->getImage();
    }
}
