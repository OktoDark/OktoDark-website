<?php

namespace App\Repository;

use App\Entity\Careers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Careers|null find($id, $lockMode = null, $lockVersion = null)
 * @method Careers|null findOneBy(array $criteria, array $orderBy = null)
 * @method Careers[]    findAll()
 * @method Careers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CareersRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Careers::class);
    }

    public function showCareers()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT * FROM careers c 
            WHERE c.id
        ';

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }
}
