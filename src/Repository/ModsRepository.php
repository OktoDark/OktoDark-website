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
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ModsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mods::class);
    }

    /**
     * SEARCH MODS WITH FILTERS (category slug, compatibility, author).
     */
    public function searchMods(
        ?string $q,
        int $limit,
        int $offset,
        array $categories = [],
        array $compatibilities = [],
        array $authors = [],
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT DISTINCT m.*
            FROM mods m
            LEFT JOIN mods_categories mc ON mc.mods_id = m.id
            LEFT JOIN mod_category c ON c.id = mc.mod_category_id
            WHERE 1=1
        ';

        $params = [];

        // TEXT SEARCH
        if ($q) {
            $sql .= ' AND (m.name LIKE :q OR m.description LIKE :q)';
            $params['q'] = "%$q%";
        }

        // CATEGORY FILTER BY SLUG
        if (!empty($categories)) {
            $quoted = [];
            foreach ($categories as $slug) {
                $quoted[] = $conn->quote($slug);
            }
            $sql .= ' AND c.slug IN ('.implode(',', $quoted).')';
        }

        // COMPATIBILITY FILTER (JSON)
        if (!empty($compatibilities)) {
            foreach ($compatibilities as $ver) {
                $json = $conn->quote(json_encode([$ver]));
                $sql .= " AND JSON_CONTAINS(m.compatible, $json) = 1";
            }
        }

        // AUTHOR FILTER
        if (!empty($authors)) {
            $ids = array_map('intval', $authors);
            $sql .= ' AND m.author_id IN ('.implode(',', $ids).')';
        }

        // ORDER + PAGINATION
        $sql .= " ORDER BY m.updated_at DESC
                  LIMIT $limit OFFSET $offset";

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        return $this->hydrateResults($rows);
    }

    /**
     * COUNT MODS WITH FILTERS.
     */
    public function countMods(
        ?string $q,
        array $categories = [],
        array $compatibilities = [],
        array $authors = [],
    ): int {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT COUNT(DISTINCT m.id) AS total
            FROM mods m
            LEFT JOIN mods_categories mc ON mc.mods_id = m.id
            LEFT JOIN mod_category c ON c.id = mc.mod_category_id
            WHERE 1=1
        ';

        $params = [];

        // TEXT SEARCH
        if ($q) {
            $sql .= ' AND (m.name LIKE :q OR m.description LIKE :q)';
            $params['q'] = "%$q%";
        }

        // CATEGORY FILTER BY SLUG
        if (!empty($categories)) {
            $quoted = [];
            foreach ($categories as $slug) {
                $quoted[] = $conn->quote($slug);
            }
            $sql .= ' AND c.slug IN ('.implode(',', $quoted).')';
        }

        // COMPATIBILITY FILTER
        if (!empty($compatibilities)) {
            foreach ($compatibilities as $ver) {
                $json = $conn->quote(json_encode([$ver]));
                $sql .= " AND JSON_CONTAINS(m.compatible, $json) = 1";
            }
        }

        // AUTHOR FILTER
        if (!empty($authors)) {
            $ids = array_map('intval', $authors);
            $sql .= ' AND m.author_id IN ('.implode(',', $ids).')';
        }

        return (int) $conn->executeQuery($sql, $params)->fetchOne();
    }

    /**
     * HYDRATE RAW SQL RESULTS INTO ENTITIES.
     */
    private function hydrateResults(array $rows): array
    {
        $mods = [];
        $em = $this->getEntityManager();

        foreach ($rows as $row) {
            $mods[] = $em->getRepository(Mods::class)->find($row['id']);
        }

        return $mods;
    }

    /**
     * GET ALL UNIQUE COMPATIBILITY VERSIONS.
     */
    public function findAllUniqueCompatibilities(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT compatible FROM mods WHERE compatible IS NOT NULL';
        $rows = $conn->executeQuery($sql)->fetchAllAssociative();

        $versions = [];

        foreach ($rows as $row) {
            $decoded = json_decode($row['compatible'], true);
            if (\is_array($decoded)) {
                foreach ($decoded as $v) {
                    $versions[] = $v;
                }
            }
        }

        $versions = array_unique($versions);
        natsort($versions);

        return array_values($versions);
    }

    public function findAllUniqueAuthors(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT DISTINCT author_id FROM mods WHERE author_id IS NOT NULL';
        $rows = $conn->executeQuery($sql)->fetchAllAssociative();

        $authors = [];
        $em = $this->getEntityManager();

        foreach ($rows as $row) {
            $authors[] = $em->getRepository(User::class)->find($row['author_id']);
        }

        return $authors;
    }

    /**
     * Find all mods with author relationship joined.
     *
     * @return Mods[]
     */
    public function findAllWithAuthor(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.author', 'a')
            ->addSelect('a')
            ->orderBy('m.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a single mod by ID with author relationship joined.
     */
    public function findWithAuthor(int $id): ?Mods
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.author', 'a')
            ->addSelect('a')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
