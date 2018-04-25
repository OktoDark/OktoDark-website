<?php

namespace App\Repository;

use App\Entity\OurGames;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method OurGames|null find($id, $lockMode = null, $lockVersion = null)
 * @method OurGames|null findOneBy(array $criteria, array $orderBy = null)
 * @method OurGames[]    findAll()
 * @method OurGames[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OurGamesRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
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
