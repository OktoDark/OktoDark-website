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

use App\Dto\NextEpisodeItem;
use App\Dto\RecentlyWatchedItem;
use App\Entity\Episode;
use App\Entity\User;
use App\Enum\WatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EpisodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Episode::class);
    }

    /* ---------------------------------------------------------
     * BASIC QUERIES
     * --------------------------------------------------------- */

    /**
     * Finds the single next upcoming unwatched episode for each of the user's active shows.
     * Prevents listing multiple sequential unwatched episodes of the same show.
     *
     * @return NextEpisodeItem[]
     */
    public function findNextEpisodes(User $user): array
    {
        $qb = $this->createQueryBuilder('e');

        $rows = $qb
            ->select('t.id AS tvId', 'metaTv.title AS showTitle', 'metaTv.image AS coverUrl')
            ->addSelect('metaSeason.seasonNumber AS season', 'metaEp.episodeNumber AS episode', 'metaEp.releaseDate AS airDate')
            ->join('e.relatedSeason', 's')
            ->join('s.mediaMetadata', 'metaSeason')
            ->join('s.relatedTv', 't')
            ->join('t.mediaMetadata', 'metaTv')
            ->join('e.mediaMetadata', 'metaEp')
            ->where('t.user = :u')
            ->andWhere('e.endDate IS NULL')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('t.status', ':statusInProgress'),
                    $qb->expr()->andX(
                        $qb->expr()->eq('t.status', ':statusPlanning'),
                        $qb->expr()->gt('SIZE(t.seasons)', 0)
                    )
                )
            )
            ->setParameter('u', $user)
            ->setParameter('statusInProgress', WatchStatus::IN_PROGRESS)
            ->setParameter('statusPlanning', WatchStatus::PLANNING)
            ->orderBy('metaSeason.seasonNumber', 'ASC')
            ->addOrderBy('metaEp.episodeNumber', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $nextByShow = [];
        foreach ($rows as $row) {
            $tvId = (int) $row['tvId'];
            if (!isset($nextByShow[$tvId])) {
                $nextByShow[$tvId] = $row;
            }
        }

        return array_map(static function (array $row) {
            return new NextEpisodeItem(
                tvId: (int) $row['tvId'],
                showTitle: $row['showTitle'] ?? 'Untitled',
                coverUrl: $row['coverUrl'],
                season: (int) $row['season'],
                episode: (int) $row['episode'],
                airDate: $row['airDate'],
            );
        }, \array_slice($nextByShow, 0, 10, true));
    }

    /**
     * Recently Watched — modern DTO version.
     * Optimized to select properties directly to avoid loading deep database entities.
     *
     * @return RecentlyWatchedItem[]
     */
    public function findRecentlyWatched(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('t.id AS tvId', 'metaTv.title AS showTitle', 'metaEp.title AS episodeTitle')
            ->addSelect('metaSeason.seasonNumber AS seasonNumber', 'metaEp.episodeNumber AS episodeNumber')
            ->addSelect('metaTv.image AS coverUrl', 'e.endDate AS watchedAt')
            ->join('e.relatedSeason', 's')
            ->join('s.mediaMetadata', 'metaSeason')
            ->join('s.relatedTv', 't')
            ->join('t.mediaMetadata', 'metaTv')
            ->join('e.mediaMetadata', 'metaEp')
            ->where('t.user = :u')
            ->andWhere('e.endDate IS NOT NULL')
            ->orderBy('e.endDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setParameter('u', $user)
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row) {
            return new RecentlyWatchedItem(
                tvId: (int) $row['tvId'],
                showTitle: $row['showTitle'],
                episodeTitle: $row['episodeTitle'],
                formattedEpisode: \sprintf(
                    'S%02dE%02d',
                    $row['seasonNumber'],
                    $row['episodeNumber']
                ),
                coverUrl: $row['coverUrl'],
                watchedAt: $row['watchedAt']
            );
        }, $rows);
    }

    /* ---------------------------------------------------------
     * PURE COUNT HELPERS
     * --------------------------------------------------------- */

    public function countTotalEpisodesForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWatchedEpisodesForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumWatchedRuntime(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COALESCE(SUM(metaEp.runtime), 0)')
            ->join('e.mediaMetadata', 'metaEp')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /* ---------------------------------------------------------
     * EPISODE FETCHING
     * --------------------------------------------------------- */

    public function findEpisodeForUser(User $user, int $showId, int $season, int $episode): ?Episode
    {
        return $this->createQueryBuilder('e')
            ->join('e.mediaMetadata', 'metaEp')
            ->join('e.relatedSeason', 's')
            ->join('s.mediaMetadata', 'metaSeason')
            ->join('s.relatedTv', 't')
            ->where('t.id = :show')
            ->andWhere('t.user = :user')
            ->andWhere('metaSeason.seasonNumber = :season')
            ->andWhere('metaEp.episodeNumber = :episode')
            ->setParameter('show', $showId)
            ->setParameter('user', $user)
            ->setParameter('season', $season)
            ->setParameter('episode', $episode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Next episode (same season only).
     * Season rollover handled in TV entity.
     */
    public function findNextEpisode(int $showId, int $season, int $episode, User $user): ?Episode
    {
        return $this->createQueryBuilder('e')
            ->join('e.mediaMetadata', 'metaEp')
            ->join('e.relatedSeason', 's')
            ->join('s.mediaMetadata', 'metaSeason')
            ->join('s.relatedTv', 't')
            ->where('t.id = :show')
            ->andWhere('t.user = :user')
            ->andWhere('metaSeason.seasonNumber = :season')
            ->andWhere('metaEp.episodeNumber = :nextEp')
            ->setParameter('show', $showId)
            ->setParameter('user', $user)
            ->setParameter('season', $season)
            ->setParameter('nextEp', $episode + 1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findEpisodesInSeason(User $user, int $showId, int $season): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.mediaMetadata', 'metaEp')
            ->join('e.relatedSeason', 's')
            ->join('s.mediaMetadata', 'metaSeason')
            ->join('s.relatedTv', 't')
            ->where('t.id = :show')
            ->andWhere('t.user = :user')
            ->andWhere('metaSeason.seasonNumber = :season')
            ->orderBy('metaEp.episodeNumber', 'ASC')
            ->setParameter('show', $showId)
            ->setParameter('user', $user)
            ->setParameter('season', $season)
            ->getQuery()
            ->getResult();
    }

    public function findLastWatchedEpisode(User $user, int $showId): ?Episode
    {
        return $this->createQueryBuilder('e')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.id = :show')
            ->andWhere('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->orderBy('e.endDate', 'DESC')
            ->setParameter('show', $showId)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastWatchedEpisodeByNumber(User $user, int $showId): ?Episode
    {
        return $this->createQueryBuilder('e')
            ->join('e.relatedSeason', 's')
            ->join('s.mediaMetadata', 'metaSeason')
            ->join('s.relatedTv', 't')
            ->join('e.mediaMetadata', 'm')
            ->where('t.id = :show')
            ->andWhere('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->orderBy('metaSeason.seasonNumber', 'DESC')
            ->addOrderBy('m.episodeNumber', 'DESC')
            ->addOrderBy('e.endDate', 'DESC')
            ->setParameter('show', $showId)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countWatchedEpisodesForShow(User $user, int $showId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.id = :show')
            ->andWhere('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->setParameter('show', $showId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotalEpisodesForShow(User $user, int $showId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.id = :show')
            ->andWhere('t.user = :user')
            ->setParameter('show', $showId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /* ---------------------------------------------------------
     * PREMIUM DASHBOARD — CHARTS
     * --------------------------------------------------------- */

    public function getEpisodesWatchedByDay(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('DATE(e.endDate) AS date, COUNT(e.id) AS episodesWatched')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn ($r) => [
            'date' => $r['date'],
            'episodesWatched' => (int) $r['episodesWatched'],
        ], $rows);
    }

    public function getEpisodesWatchedByMonth(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select("DATE_FORMAT(e.endDate, '%Y-%m') AS month, COUNT(e.id) AS episodesWatched")
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->where('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn ($r) => [
            'month' => $r['month'],
            'episodesWatched' => (int) $r['episodesWatched'],
        ], $rows);
    }

    public function getDailyHeatmap(User $user): array
    {
        return $this->getEpisodesWatchedByDay($user);
    }
}
