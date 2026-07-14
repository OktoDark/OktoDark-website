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

use App\Entity\Game;
use App\Entity\User;
use App\Enum\WatchStatus;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractMediaRepository<Game>
 */
class GameRepository extends AbstractMediaRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /* ---------------------------------------------------------
     * BASIC COUNTS
     * --------------------------------------------------------- */

    public function countUserGames(User $user): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUserCompletedGames(User $user): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.user = :user')
            ->andWhere('g.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', WatchStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Generic status counter.
     */
    public function countUserGamesByStatus(User $user, WatchStatus $status): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count games currently being played.
     */
    public function countUserPlayingGames(User $user): int
    {
        return $this->countUserGamesByStatus($user, WatchStatus::IN_PROGRESS);
    }

    /* ---------------------------------------------------------
     * GAME LIST (search + sorting)
     * --------------------------------------------------------- */

    public function findGameList(
        User $user,
        ?string $search = null,
        ?string $sort = null,
        ?WatchStatus $status = null
    ): array {
        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('g.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('LOWER(meta.title) LIKE :search OR LOWER(meta.alternativeTitles) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        switch ($sort) {
            case 'title':
                $qb->orderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'release_date':
                $qb->orderBy('meta.releaseDate', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'runtime':
                $qb->orderBy('meta.runtimeEstimate', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'score':
                $qb->orderBy('g.score', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'status':
                $qb->orderBy('g.status', 'ASC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'progress':
                $qb->orderBy('g.progress', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            default:
                // Default: newest added first
                $qb->orderBy('g.createdAt', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /* ---------------------------------------------------------
     * PREMIUM DASHBOARD — TOP GAMES
     * --------------------------------------------------------- */

    public function getTopGamesByPlayTime(User $user, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.mediaMetadata', 'meta')
            ->select('g.id AS id, meta.title AS title, meta.image AS coverUrl')
            ->addSelect('meta.runtimeEstimate AS runtimeEstimate')
            ->addSelect('g.progress AS progress')
            ->addSelect('g.score AS score')
            ->addSelect('g.status AS status')
            ->where('g.user = :user')
            ->andWhere('g.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                WatchStatus::IN_PROGRESS,
                WatchStatus::COMPLETED,
            ])
            ->orderBy('meta.runtimeEstimate', 'DESC')
            ->setMaxResults($limit);

        return array_map(static function (array $row) {
            $hours = round(($row['runtimeEstimate'] * ($row['progress'] / 100)) / 60, 1);

            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'coverUrl' => $row['coverUrl'],
                'score' => $row['score'],
                'status' => $row['status'],
                'progress' => (int) $row['progress'],
                'hoursPlayed' => $hours,
            ];
        }, $qb->getQuery()->getArrayResult());
    }

    /* ---------------------------------------------------------
     * PREMIUM DASHBOARD — TOP GENRES
     * --------------------------------------------------------- */

    public function getTopGameGenres(User $user, int $limit = 6): array
    {
        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.mediaMetadata', 'meta')
            ->select('meta.genre AS genre')
            ->addSelect('COUNT(g.id) AS count')
            ->addSelect('COALESCE(SUM(meta.runtimeEstimate), 0) AS minutes')
            ->where('g.user = :user')
            ->groupBy('genre')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user);

        return array_map(static function (array $row) {
            return [
                'genre' => $row['genre'],
                'count' => (int) $row['count'],
                'hoursPlayed' => round($row['minutes'] / 60, 1),
            ];
        }, $qb->getQuery()->getArrayResult());
    }
}
