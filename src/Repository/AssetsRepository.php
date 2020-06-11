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

use App\Entity\Assets;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Assets|null find($id, $lockMode = null, $lockVersion = null)
 * @method Assets|null findOneBy(array $criteria, array $orderBy = null)
 * @method Assets[]    findAll()
 * @method Assets[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AssetsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assets::class);
    }

    public function findAllAssets()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT * FROM assets s
            WHERE s.id
            ORDER BY s.id ASC
        ';

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }

    public function showFigureCompatible()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT figurecompatible FROM assets c
            WHERE c.id
            ORDER BY c.id ASC
        ';

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }


    // /**
    //  * @return Assets[] Returns an array of Assets objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Assets
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
