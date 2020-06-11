<?php

/**
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 13.01.2020, 06:51
 */

namespace App\Repository;

use App\Entity\OurGames;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method OurGames|null find($id, $lockMode = null, $lockVersion = null)
 * @method OurGames|null findOneBy(array $criteria, array $orderBy = null)
 * @method OurGames[]    findAll()
 * @method OurGames[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OurGamesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OurGames::class);
    }

    public function AllGames()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT * FROM our_games g 
            WHERE g.id
            ORDER BY g.id ASC
        ';

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }
}
