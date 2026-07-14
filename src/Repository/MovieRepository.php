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

use App\Entity\Movie;
use App\Entity\User;
use App\Enum\WatchStatus;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractMediaRepository<Movie>
 */
class MovieRepository extends AbstractMediaRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Movie::class);
    }

    /**
     * Count all movies for a user.
     */
    public function countUserMovies(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count completed movies.
     */
    public function countUserCompletedMovies(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.user = :user')
            ->andWhere('m.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', WatchStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count movies by status (generic).
     */
    public function countUserMoviesByStatus(User $user, WatchStatus $status): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.user = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count movies currently being watched.
     */
    public function countUserWatchingMovies(User $user): int
    {
        return $this->countUserMoviesByStatus($user, WatchStatus::IN_PROGRESS);
    }

    /**
     * Fetch all movies for a user.
     */
    public function findUserMovies(User $user): array
    {
        return $this->findMovieList($user);
    }

    /**
     * Fetch movies with optional search + sorting.
     */
    public function findMovieList(
        User $user,
        ?string $search = null,
        ?string $sort = null,
        ?WatchStatus $status = null,
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('m.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('m.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('LOWER(meta.title) LIKE :search OR LOWER(meta.alternativeTitles) LIKE :search')
                ->setParameter('search', '%'.strtolower($search).'%');
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
                $qb->orderBy('m.score', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'status':
                $qb->orderBy('m.status', 'ASC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            default:
                // Default: newest added first
                $qb->orderBy('m.createdAt', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Top movies by watch time (for stats dashboard).
     */
    public function getTopMoviesByWatchTime(User $user, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.mediaMetadata', 'meta')
            ->select('m.id AS id, meta.title AS title, meta.image AS coverUrl')
            ->addSelect('meta.runtimeEstimate AS runtimeEstimate')
            ->addSelect('m.score AS score')
            ->addSelect('m.status AS status')
            ->where('m.user = :user')
            ->andWhere('m.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                WatchStatus::IN_PROGRESS,
                WatchStatus::COMPLETED,
            ])
            ->orderBy('meta.runtimeEstimate', 'DESC')
            ->setMaxResults($limit);

        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'coverUrl' => $row['coverUrl'],
                'score' => $row['score'],
                'status' => $row['status'],
                'hoursWatched' => round($row['runtimeEstimate'] / 60, 1),
            ];
        }, $qb->getQuery()->getArrayResult());
    }

    /**
     * Top genres for movies.
     */
    public function getTopMovieGenres(User $user, int $limit = 6): array
    {
        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.mediaMetadata', 'meta')
            ->select('meta.genre AS genre')
            ->addSelect('COUNT(m.id) AS count')
            ->addSelect('COALESCE(SUM(meta.runtimeEstimate), 0) AS minutes')
            ->where('m.user = :user')
            ->groupBy('genre')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user);

        return array_map(static function (array $row) {
            return [
                'genre' => $row['genre'],
                'count' => (int) $row['count'],
                'hoursWatched' => round($row['minutes'] / 60, 1),
            ];
        }, $qb->getQuery()->getArrayResult());
    }

    public function findByMediaIdAndUser(string $mediaId, User $user): ?Movie
    {
        return $this->createQueryBuilder('m')
            ->join('m.mediaMetadata', 'meta')
            ->where('meta.mediaId = :mediaId')
            ->andWhere('m.user = :user')
            ->setParameter('mediaId', $mediaId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
