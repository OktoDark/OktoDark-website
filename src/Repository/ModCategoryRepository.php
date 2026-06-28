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

use App\Entity\ModCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ModCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModCategory::class);
    }

    public function findTopCategories(int $limit = 4): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
        SELECT c.*, COUNT(m.id) AS mod_count
        FROM mod_category c
        LEFT JOIN mods_categories mc ON mc.mod_category_id = c.id
        LEFT JOIN mods m ON m.id = mc.mods_id
        GROUP BY c.id
        ORDER BY mod_count DESC
        LIMIT $limit
    ";

        $rows = $conn->executeQuery($sql)->fetchAllAssociative();

        $categories = [];
        $em = $this->getEntityManager();

        foreach ($rows as $row) {
            $categories[] = $em->getRepository(ModCategory::class)->find($row['id']);
        }

        return $categories;
    }

    public function findTopCategoriesWithCount(int $limit = 4): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
        SELECT c.*, COUNT(m.id) AS mod_count
        FROM mod_category c
        LEFT JOIN mods_categories mc ON mc.mod_category_id = c.id
        LEFT JOIN mods m ON m.id = mc.mods_id
        GROUP BY c.id
        ORDER BY mod_count DESC
        LIMIT $limit
    ";

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    public function findAllCategoriesWithCount(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT c.*, COUNT(m.id) AS mod_count
        FROM mod_category c
        LEFT JOIN mods_categories mc ON mc.mod_category_id = c.id
        LEFT JOIN mods m ON m.id = mc.mods_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ';

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }
}
