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

use App\Entity\Board;
use App\Entity\CardLabel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardLabel>
 */
class CardLabelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardLabel::class);
    }

    /**
     * Find labels in a board.
     */
    public function findByBoard(Board $board): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.board = :board')
            ->setParameter('board', $board)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
