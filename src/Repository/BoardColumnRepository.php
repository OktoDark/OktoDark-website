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

use App\Entity\BoardColumn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoardColumn>
 */
class BoardColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoardColumn::class);
    }

    /**
     * Get next position for a new column in board.
     */
    public function getNextPosition(int $boardId): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('MAX(c.position) as max_pos')
            ->where('c.board = :boardId')
            ->setParameter('boardId', $boardId)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return ($result ?? 0) + 1;
    }
}
