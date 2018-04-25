<?php

namespace App\Repository;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class NewsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, News::class);
    }

    public function showAllNews()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT * FROM news n 
            WHERE n.id
            ORDER BY n.id ASC
        ';

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }
}
