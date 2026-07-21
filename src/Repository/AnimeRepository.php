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

use App\Dto\ContinueWatchingItem;
use App\Entity\Anime;
use App\Entity\User;
use App\Enum\WatchStatus;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractMediaRepository<Anime>
 */
class AnimeRepository extends AbstractMediaRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Anime::class);
    }

    /**
     * Count all anime for a user.
     */
    public function countUserAnime(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count completed anime.
     */
    public function countUserCompletedAnime(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', WatchStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count anime by status (generic).
     */
    public function countUserAnimeByStatus(User $user, WatchStatus $status): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count anime currently being watched.
     */
    public function countUserWatchingAnime(User $user): int
    {
        return $this->countUserAnimeByStatus($user, WatchStatus::IN_PROGRESS);
    }

    /**
     * Retrieve paginated anime for the "Continue Watching" dashboard section.
     *
     * @return ContinueWatchingItem[]
     */
    public function findContinueWatching(User $user, int $offset = 0, int $limit = 7): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('a.user = :u')
            ->andWhere('a.status = :status')
            ->setParameter('u', $user)
            ->setParameter('status', WatchStatus::IN_PROGRESS)
            ->addOrderBy('a.progressedAt', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $animeList = $qb->getQuery()->getResult();

        $items = [];

        foreach ($animeList as $anime) {
            $progressPercent = $anime->getProgress();
            $isCompleted = WatchStatus::COMPLETED === $anime->getStatus();
            $isInProgress = WatchStatus::IN_PROGRESS === $anime->getStatus();

            if ($isCompleted) {
                continue;
            }

            $items[] = new ContinueWatchingItem(
                tvId: $anime->getId(),
                title: $anime->getTitle() ?? 'Untitled',
                coverUrl: $anime->getCoverUrl() ?? '',
                nextSeason: null,
                nextEpisode: null,
                isCompleted: $isCompleted,
                isInProgress: $isInProgress,
                progressPercent: $progressPercent,
                recentWatchedAt: $anime->getProgressedAt() ?? $anime->getCreatedAt()
            );
        }

        return $items;
    }

    /**
     * Count total "Continue Watching" anime associated with a user.
     */
    public function countContinueWatching(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', WatchStatus::IN_PROGRESS)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Fetch anime list with optional search + sorting.
     */
    public function findAnimeList(
        User $user,
        ?string $search = null,
        ?string $sort = null,
        ?WatchStatus $status = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('a.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('LOWER(meta.title) LIKE :search OR LOWER(meta.alternativeTitles) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
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
                $qb->orderBy('a.score', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'progress':
                $qb->orderBy('a.progress', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'status':
                $qb->orderBy('a.status', 'ASC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            default:
                // Default: newest added first
                $qb->orderBy('a.createdAt', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Top anime by watch time (for stats dashboard).
     */
    public function getTopAnimeByWatchTime(User $user, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.mediaMetadata', 'meta')
            ->select('a.id AS id, meta.title AS title, meta.image AS coverUrl')
            ->addSelect('a.progress AS progress')
            ->addSelect('meta.runtimeEstimate AS runtimeEstimate')
            ->where('a.user = :user')
            ->andWhere('a.progress > 0')
            ->setParameter('user', $user)
            ->orderBy('a.progress', 'DESC')
            ->setMaxResults($limit);

        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'coverUrl' => $row['coverUrl'],
                'progress' => (int) $row['progress'],
                'hoursWatched' => round(($row['runtimeEstimate'] * ($row['progress'] / 100)) / 60, 1),
            ];
        }, $qb->getQuery()->getArrayResult());
    }

    /**
     * Top genres for anime.
     */
    public function getTopAnimeGenres(User $user, int $limit = 6): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.mediaMetadata', 'meta')
            ->select('meta.genre AS genre')
            ->addSelect('COUNT(a.id) AS count')
            ->addSelect('COALESCE(SUM(meta.runtimeEstimate), 0) AS minutes')
            ->where('a.user = :user')
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
}
