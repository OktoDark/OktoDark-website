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

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
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
