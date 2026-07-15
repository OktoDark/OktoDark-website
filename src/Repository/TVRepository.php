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
use App\Entity\TV;
use App\Entity\User;
use App\Enum\WatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TVRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TV::class);
    }

    private function episodes(): EpisodeRepository
    {
        return $this->getEntityManager()->getRepository(\App\Entity\Episode::class);
    }

    private function seasons(): SeasonRepository
    {
        return $this->getEntityManager()->getRepository(\App\Entity\Season::class);
    }

    /**
     * Retrieve TV shows for the dashboard with optional search and sorting.
     */
    public function findDashboardShows(
        User $user,
        ?string $search = null,
        ?string $sort = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('t.user = :u')
            ->setParameter('u', $user);

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
                $qb->orderBy('t.progress', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            case 'status':
                $qb->orderBy('t.status', 'ASC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            default:
                $qb->orderBy('t.createdAt', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Build a paginated list of shows the user is currently watching.
     *
     * Orders by the show's general tracking state and limits performance impact
     * by executing calculations only on the requested offset chunk.
     *
     * @return ContinueWatchingItem[]
     */
    public function findContinueWatching(User $user, int $offset = 0, int $limit = 7): array
    {
        $qb = $this->createQueryBuilder('t');

        // To prevent empty gaps in pagination, we filter out empty PLANNING shows
        // directly in DQL using a EXISTS/size subquery.
        $qb->innerJoin('t.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('t.user = :u')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('t.status', ':statusInProgress'),
                    $qb->expr()->andX(
                        $qb->expr()->eq('t.status', ':statusPlanning'),
                        $qb->expr()->gt('SIZE(t.seasons)', 0) // Only fetch PLANNING shows with seasons
                    )
                )
            )
            ->setParameter('u', $user)
            ->setParameter('statusInProgress', WatchStatus::IN_PROGRESS)
            ->setParameter('statusPlanning', WatchStatus::PLANNING)
            ->orderBy('t.progressedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $shows = $qb->getQuery()->getResult();

        $items = [];
        $epRepo = $this->episodes();

        foreach ($shows as $show) {
            $showId = $show->getId();
            $meta = $show->getMediaMetadata();

            // Next episode details
            $next = $show->getNextEpisode();
            $nextSeason = $next['season'] ?? null;
            $nextEpisode = $next['episode'] ?? null;

            // Episode progress counters
            $watched = $epRepo->countWatchedEpisodesForShow($user, $showId);
            $total = $epRepo->countTotalEpisodesForShow($user, $showId);

            $isCompleted = ($total > 0 && $watched >= $total);

            // Skip shows that are fully watched and completed
            if ($isCompleted) {
                continue;
            }

            $progressPercent = $total > 0
                ? (int) min(100, round(($watched / $total) * 100))
                : 0;

            $isInProgress = WatchStatus::IN_PROGRESS === $show->getStatus();

            $items[] = new ContinueWatchingItem(
                tvId: $showId,
                title: $meta->getTitle(),
                coverUrl: $meta->getImage() ?? '',
                nextSeason: $nextSeason,
                nextEpisode: $nextEpisode,
                isCompleted: $isCompleted,
                isInProgress: $isInProgress,
                progressPercent: $progressPercent
            );
        }

        return $items;
    }

    /**
     * Count total "Continue Watching" shows associated with a user.
     */
    public function countContinueWatching(User $user): int
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('t.status', ':statusInProgress'),
                    $qb->expr()->andX(
                        $qb->expr()->eq('t.status', ':statusPlanning'),
                        $qb->expr()->gt('SIZE(t.seasons)', 0)
                    )
                )
            )
            ->setParameter('user', $user)
            ->setParameter('statusInProgress', WatchStatus::IN_PROGRESS)
            ->setParameter('statusPlanning', WatchStatus::PLANNING);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count total TV shows associated with a user.
     */
    public function countUserShows(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retrieve a paginated list of TV shows for a user with optional filters.
     *
     * @return array{items: TV[], total: int}
     */
    public function findUserList(
        User $user,
        ?string $search = null,
        ?string $country = null,
        ?string $actor = null,
        int $page = 1,
        int $limit = 24,
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $qb = $conn->createQueryBuilder();

        $qb->select('t.id AS tv_id')
            ->from('tracking_tv', 't')
            ->innerJoin('t', 'tracking_item', 'ti', 'ti.id = t.media_metadata_id')
            ->where('t.user_id = :user')
            ->setParameter('user', $user->getId());

        if ($search) {
            $qb->andWhere('LOWER(ti.title) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        if ($country) {
            $qb->andWhere('LOWER(ti.country) LIKE :country')
                ->setParameter('country', '%'.mb_strtolower($country).'%');
        }

        if ($actor) {
            $qb->andWhere('JSON_SEARCH(ti.cast, "one", :actor, NULL, "$[*].name") IS NOT NULL')
                ->setParameter('actor', $actor);
        }

        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT t.id)');
        $total = (int) $countQb->executeQuery()->fetchOne();

        $qb->orderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit);

        $rows = $qb->executeQuery()->fetchAllAssociative();
        $tvIds = array_map(static fn ($row) => $row['tv_id'], $rows);

        if (empty($tvIds)) {
            return ['items' => [], 'total' => 0];
        }

        $tvs = $this->createQueryBuilder('t')
            ->innerJoin('t.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $tvIds)
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return ['items' => $tvs, 'total' => $total];
    }

    /**
     * Count user's TV shows filtered by a specific watch status.
     */
    public function countShowsByStatus(User $user, WatchStatus $status): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Identify the user’s most-watched shows ranked by watch time.
     */
    public function getTopShowsByWatchTime(User $user, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.mediaMetadata', 'meta')
            ->innerJoin('t.seasons', 's')
            ->innerJoin('s.episodes', 'e')
            ->innerJoin('e.mediaMetadata', 'em')
            ->select('t.id AS id, meta.title AS title, meta.image AS coverUrl')
            ->addSelect('COUNT(e.id) AS episodesWatched')
            ->addSelect('COALESCE(SUM(em.runtime), 0) AS minutesWatched')
            ->where('t.user = :user')
            ->andWhere('e.endDate IS NOT NULL')
            ->groupBy('t.id, title, coverUrl')
            ->orderBy('minutesWatched', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user);

        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'coverUrl' => $row['coverUrl'],
                'episodesWatched' => (int) $row['episodesWatched'],
                'hoursWatched' => round($row['minutesWatched'] / 60, 1),
            ];
        }, $qb->getQuery()->getArrayResult());
    }

    /**
     * Determine the user’s most frequent genres ranked by watch volume.
     */
    public function getTopGenres(User $user, int $limit = 6): array
    {
        $rows = $this->createQueryBuilder('t')
            ->innerJoin('t.mediaMetadata', 'meta')
            ->select('meta.genres AS genres, meta.runtimeEstimate AS minutes')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $aggregated = [];

        foreach ($rows as $row) {
            $genres = $row['genres'] ?? [];

            if (!\is_array($genres)) {
                continue;
            }

            foreach ($genres as $genre) {
                if (null === $genre || '' === $genre) {
                    continue;
                }

                if (!isset($aggregated[$genre])) {
                    $aggregated[$genre] = ['count' => 0, 'minutes' => 0];
                }

                ++$aggregated[$genre]['count'];
                $aggregated[$genre]['minutes'] += (int) ($row['minutes'] ?? 0);
            }
        }

        uasort($aggregated, static fn ($a, $b) => $b['count'] <=> $a['count']);

        $top = \array_slice($aggregated, 0, $limit, true);

        return array_map(static fn ($genre, $data) => [
            'genre' => $genre,
            'count' => $data['count'],
            'hoursWatched' => round($data['minutes'] / 60, 1),
        ], array_keys($top), array_values($top));
    }
}
