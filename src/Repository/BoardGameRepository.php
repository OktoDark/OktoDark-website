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

use App\Entity\BoardGame;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractMediaRepository<BoardGame>
 */
class BoardGameRepository extends AbstractMediaRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoardGame::class);
    }
}
