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

use App\Entity\ModRating;
use App\Entity\Mods;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModRating>
 */
class ModRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModRating::class);
    }

    /**
     * Check if an IP already rated a specific mod.
     */
    public function findExistingRating(Mods $mod, string $ip): ?ModRating
    {
        return $this->findOneBy([
            'mod' => $mod,
            'ip' => $ip,
        ]);
    }

    /**
     * Get all ratings for a mod.
     */
    public function getRatingsForMod(Mods $mod): array
    {
        return $this->findBy(['mod' => $mod]);
    }

    /**
     * Calculate average rating for a mod (1–10 scale).
     */
    public function calculateAverageRating(Mods $mod): float
    {
        $avg = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS avgRating')
            ->where('r.mod = :mod')
            ->setParameter('mod', $mod)
            ->getQuery()
            ->getSingleScalarResult();

        return $avg ? (float) $avg : 0.0;
    }

    /**
     * Rating breakdown for 1–10 scale.
     *
     * Returns array like:
     * [
     *   ['rating' => 10, 'total' => X],
     *   ['rating' => 9,  'total' => Y],
     *   ...
     *   ['rating' => 1,  'total' => N],
     * ]
     */
    public function getRatingStats(Mods $mod): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.rating AS rating, COUNT(r.id) AS total')
            ->where('r.mod = :mod')
            ->setParameter('mod', $mod)
            ->groupBy('r.rating')
            ->getQuery()
            ->getResult();

        // Initialize full 1–10 map
        $stats = [];
        for ($i = 10; $i >= 1; --$i) {
            $stats[$i] = 0;
        }

        foreach ($qb as $row) {
            $rating = (int) $row['rating'];
            if ($rating < 1 || $rating > 10) {
                continue;
            }
            $stats[$rating] = (int) $row['total'];
        }

        // Format for JS
        $formatted = [];
        foreach ($stats as $rating => $total) {
            $formatted[] = [
                'rating' => $rating,
                'total' => $total,
            ];
        }

        return $formatted;
    }
}
