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

use App\Dto\SeasonGridItem;
use App\Dto\TvShowGridItem;
use App\Entity\Season;
use App\Entity\User;
use App\Enum\WatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Season::class);
    }

    private function episodes(): EpisodeRepository
    {
        return $this->getEntityManager()->getRepository(\App\Entity\Episode::class);
    }

    /* ---------------------------------------------------------
     * BASIC SEASON LIST (filters + sorting)
     * --------------------------------------------------------- */
    public function findSeasonsForUser(
        User $user,
        ?WatchStatus $status = null,
        ?string $search = null,
        ?string $sort = null,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.relatedTv', 'tv')
            ->innerJoin('s.mediaMetadata', 'meta')
            ->addSelect('tv', 'meta')
            ->where('tv.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('s.status = :status')
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

            case 'progress':
                $qb->orderBy('s.progress', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'status':
                $qb->orderBy('s.status', 'ASC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            default:
                $qb->orderBy('s.createdAt', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /* ---------------------------------------------------------
     * GROUPED SEASONS (TV Show → Seasons → Episodes)
     * --------------------------------------------------------- */
    public function findGroupedSeasons(
        User $user,
        ?WatchStatus $status = null,
        ?string $search = null,
        int $limit = 20,
        int $offset = 0,
    ): array {
        // Step 1: resolve the distinct TV shows that match the filters and
        // paginate on the SHOW level (not on the exploded season/episode rows).
        // Applying the limit directly to the one-to-many joined query would
        // truncate entire seasons for shows with many episodes (e.g. Grimm).
        $idQb = $this->createQueryBuilder('s')
            ->select('DISTINCT tv.id AS tvId')
            ->innerJoin('s.relatedTv', 'tv')
            ->innerJoin('tv.mediaMetadata', 'meta')
            ->innerJoin('s.mediaMetadata', 'seasonMeta')
            ->where('tv.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $idQb->andWhere('s.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $idQb->andWhere('LOWER(meta.title) LIKE :search OR LOWER(meta.alternativeTitles) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        $idQb->orderBy('LOWER(meta.title)', 'ASC')
            ->addOrderBy('tv.id', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $tvIds = array_map(static fn (array $row) => $row['tvId'], $idQb->getQuery()->getArrayResult());

        if ([] === $tvIds) {
            return [];
        }

        // Step 2: hydrate every season + episode for the selected shows. This
        // keeps the join multiplication safe because no result limit is applied.
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.relatedTv', 'tv')
            ->innerJoin('tv.mediaMetadata', 'meta')
            ->innerJoin('s.mediaMetadata', 'seasonMeta')
            ->leftJoin('s.episodes', 'ep')
            ->leftJoin('ep.mediaMetadata', 'epMeta')
            ->addSelect('tv', 'meta', 'seasonMeta', 'ep', 'epMeta')
            ->where('tv.user = :user')
            ->andWhere('tv.id IN (:tvIds)')
            ->setParameter('user', $user)
            ->setParameter('tvIds', $tvIds);

        $qb->orderBy('LOWER(meta.title)', 'ASC')
            ->addOrderBy('seasonMeta.seasonNumber', 'ASC')
            ->addOrderBy('epMeta.episodeNumber', 'ASC');

        /** @var Season[] $seasons */
        $seasons = $qb->getQuery()->getResult();

        /* ---------------------------------------------------------
         * GROUP BY TV SHOW
         * --------------------------------------------------------- */
        $grouped = [];

        foreach ($seasons as $season) {
            $tv = $season->getRelatedTv();
            $meta = $tv->getMediaMetadata();

            if (!isset($grouped[$tv->getId()])) {
                $grouped[$tv->getId()] = [
                    'tv' => $tv,
                    'meta' => $meta,
                    'seasons' => [],
                ];
            }

            $grouped[$tv->getId()]['seasons'][] = $season;
        }

        /* ---------------------------------------------------------
         * HYDRATE DTOs
         * --------------------------------------------------------- */
        $result = [];
        $epRepo = $this->episodes();

        foreach ($grouped as $tvId => $data) {
            $tv = $data['tv'];
            $meta = $data['meta'];

            $seasonDtos = [];
            $totalEpisodes = 0;
            $watchedEpisodes = 0;

            foreach ($data['seasons'] as $season) {
                $seasonMeta = $season->getMediaMetadata();

                // Episode counts
                $seasonTotal = \count($season->getEpisodes());
                $seasonWatched = 0;

                foreach ($season->getEpisodes() as $ep) {
                    if ($ep->isWatched()) {
                        ++$seasonWatched;
                    }
                }

                $totalEpisodes += $seasonTotal;
                $watchedEpisodes += $seasonWatched;

                $seasonDtos[] = new SeasonGridItem(
                    seasonNumber: $seasonMeta->getSeasonNumber(),
                    title: $seasonMeta->getTitle(),
                    coverUrl: $seasonMeta->getImage(),
                    releaseDate: $seasonMeta->getReleaseDate()?->format('Y'),
                    statusLabel: $season->getStatus()->label(),
                    statusClass: $season->getStatus()->cssClass(),
                    watchedEpisodes: $seasonWatched,
                    totalEpisodes: $seasonTotal,
                    progress: $seasonTotal > 0 ? round(($seasonWatched / $seasonTotal) * 100) : 0,
                    score: $season->getScore(),
                    notes: $season->getNotes(),
                );
            }

            $result[] = new TvShowGridItem(
                id: $tv->getId(),
                title: $meta->getTitle(),
                coverUrl: $meta->getImage(),
                statusLabel: $tv->getStatus()->label(),
                statusClass: $tv->getStatus()->cssClass(),
                releaseDate: $meta->getReleaseDate()?->format('Y'),
                watchedEpisodes: $watchedEpisodes,
                totalEpisodes: $totalEpisodes,
                seasons: $seasonDtos
            );
        }

        return $result;
    }

    /* ---------------------------------------------------------
     * COUNT UNIQUE TV SHOWS (StatsService)
     * --------------------------------------------------------- */
    public function countGroupedShows(User $user, ?WatchStatus $status = null, ?string $search = null): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT tv.id)')
            ->innerJoin('s.relatedTv', 'tv')
            ->innerJoin('tv.mediaMetadata', 'meta')
            ->where('tv.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('LOWER(meta.title) LIKE :search OR LOWER(meta.alternativeTitles) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /* ---------------------------------------------------------
     * FULL TV SHOW WITH SEASONS + EPISODES
     * --------------------------------------------------------- */
    public function findTvShowWithSeasons(User $user, int $tvId): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.relatedTv', 'tv')
            ->innerJoin('s.mediaMetadata', 'seasonMeta')
            ->leftJoin('s.episodes', 'ep')
            ->leftJoin('ep.mediaMetadata', 'epMeta')
            ->addSelect('tv', 'seasonMeta', 'ep', 'epMeta')
            ->where('tv.user = :user')
            ->andWhere('tv.id = :tvId')
            ->setParameter('user', $user)
            ->setParameter('tvId', $tvId)
            ->orderBy('seasonMeta.seasonNumber', 'ASC')
            ->addOrderBy('epMeta.episodeNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
