<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Repository;

use App\Entity\OurGames;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function PlayOnline()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT our_game_play_online_id FROM our_games p
            WHERE p.our_game_play_online_id
            ORDER BY p.id ASC
        ';

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }
}
