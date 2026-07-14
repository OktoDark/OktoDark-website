<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv;

use App\Entity\TV;
use Doctrine\ORM\EntityManagerInterface;

class TvDuplicateResolver
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function resolve($meta, array $ids, $user): ?TV
    {
        return $this->em->getRepository(TV::class)->findOneBy([
            'mediaMetadata' => $meta,
            'user' => $user,
        ]);
    }

    public function createTvEntity($meta, $user): TV
    {
        $tv = new TV();
        $tv->setMediaMetadata($meta);
        $tv->setUser($user);

        return $tv;
    }
}
