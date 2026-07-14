<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Repository;

use App\Entity\Comic;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractMediaRepository<Comic>
 */
class ComicRepository extends AbstractMediaRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comic::class);
    }
}
