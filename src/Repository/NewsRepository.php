<?php

namespace App\Repository;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method News|null find($id, $lockMode = null, $lockVersion = null)
 * @method News|null findOneBy(array $criteria, array $orderBy = null)
 * @method News[]    findAll()
 * @method News[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, News::class);
    }

    public function showAllNews($createdAt): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT * FROM news n
            WHERE n.created_at <= :now
            ORDER BY n.created_at ASC
        ";

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }

}
