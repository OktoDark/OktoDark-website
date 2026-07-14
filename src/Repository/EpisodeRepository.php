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

    public function findNextEpisodes(User $user): array
    {
        $episodes = $this->createQueryBuilder('e')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->join('t.mediaMetadata', 'metaTv')
            ->join('e.mediaMetadata', 'metaEp')
            ->where('t.user = :u')
            ->andWhere('e.endDate IS NULL')
            ->orderBy('e.id', 'ASC')
            ->setParameter('u', $user)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $items = [];

        foreach ($episodes as $ep) {
            $season = $ep->getRelatedSeason();
            $tv = $season?->getRelatedTv();
            $metaTv = $tv?->getMediaMetadata();

            $items[] = new NextEpisodeItem(
                tvId: $tv?->getId() ?? 0,
                showTitle: $metaTv?->getTitle() ?? 'Untitled',
                coverUrl: $metaTv?->getImage(),
                season: $season?->getSeasonNumber(),
                episode: $ep->getEpisodeNumber(),
                airDate: $ep->getReleaseDate(),
            );
        }

        return $items;
    }

    /**
     * Recently Watched — modern DTO version.
     */
    public function findRecentlyWatched(User $user): array
    {
        $episodes = $this->createQueryBuilder('e')
            ->join('e.relatedSeason', 's')
            ->join('s.relatedTv', 't')
            ->join('t.mediaMetadata', 'metaTv')
            ->join('e.mediaMetadata', 'metaEp')
            ->where('t.user = :u')
            ->andWhere('e.endDate IS NOT NULL')
            ->orderBy('e.endDate', 'DESC')
            ->setParameter('u', $user)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $items = [];

        foreach ($episodes as $ep) {
            $season = $ep->getRelatedSeason();
            $tv = $season->getRelatedTv();
            $metaTv = $tv->getMediaMetadata();
            $metaEp = $ep->getMediaMetadata();

            $items[] = new RecentlyWatchedItem(
                tvId: $tv->getId(),
                showTitle: $metaTv->getTitle(),
                episodeTitle: $metaEp->getTitle(),
                formattedEpisode: sprintf(
                    'S%02dE%02d',
                    $season->getSeasonNumber(),
                    $metaEp->getEpisodeNumber()
                ),
                coverUrl: $metaTv->getImage(),
                watchedAt: $ep->getEndDate()
            );
        }

        return $items;
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

        return array_map(static fn($r) => [
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

        return array_map(static fn($r) => [
            'month' => $r['month'],
            'episodesWatched' => (int) $r['episodesWatched'],
        ], $rows);
    }

    public function getDailyHeatmap(User $user): array
    {
        return $this->getEpisodesWatchedByDay($user);
    }
}
