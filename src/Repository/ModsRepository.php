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

use App\Entity\Mods;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Mods|null find($id, $lockMode = null, $lockVersion = null)
 * @method Mods|null findOneBy(array $criteria, array $orderBy = null)
 * @method Mods[]    findAll()
 * @method Mods[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ModsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mods::class);
    }

    /**
     * @return Mods[] Returns an array of Mods objects
     */
    public function findAllMods()
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT * FROM mods m
            WHERE m.id
            ORDER BY m.id ASC
        ';

        $stmt = $conn->prepare($sql);

        return $stmt->fetchAll();
    }

    /*
    public function findOneBySomeField($value): ?Mods
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
