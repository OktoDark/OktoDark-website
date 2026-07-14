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

use App\Entity\User;
use App\Enum\WatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractMediaRepository extends ServiceEntityRepository
{
    public function getMediaList(
        User $user,
        ?WatchStatus $statusFilter = null,
        ?string $sortFilter = null,
        ?string $search = null,
    ): array {
        // IMPORTANT: entity alias MUST be "s" to match SeasonRepository
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.mediaMetadata', 'meta')
            ->addSelect('meta')
            ->where('s.user = :user')
            ->setParameter('user', $user);

        if ($statusFilter) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($search) {
            $qb->andWhere('LOWER(meta.title) LIKE :search')
                ->setParameter('search', '%'.strtolower($search).'%');
        }

        $this->applySorting($qb, $sortFilter);

        return $qb->getQuery()->getResult();
    }

    protected function applySorting(QueryBuilder $qb, ?string $sortFilter): void
    {
        switch ($sortFilter) {

            // ─────────────────────────────────────────────
            // TITLE SORT
            // ─────────────────────────────────────────────
            case 'title':
                $qb->orderBy('LOWER(meta.title)', 'ASC');
                break;

            // ─────────────────────────────────────────────
            // START DATE SORT
            // ─────────────────────────────────────────────
            case 'start_date':
                $qb->orderBy('s.startDate', 'ASC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            // ─────────────────────────────────────────────
            // END DATE / PROGRESSED AT SORT
            // ─────────────────────────────────────────────
            case 'end_date':
            case 'progressed_at':
                $qb->orderBy('s.'.$sortFilter, 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            // ─────────────────────────────────────────────
            // RELEASE DATE SORT (NEW)
            // ─────────────────────────────────────────────
            case 'release_date':
                $qb->orderBy('meta.releaseDate', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            // ─────────────────────────────────────────────
            // RUNTIME SORT (NEW)
            // ─────────────────────────────────────────────
            case 'runtime':
                $qb->orderBy('meta.runtime', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;

            // ─────────────────────────────────────────────
            // MEDIA ID SORT (NEW)
            // Useful for grouping TV → Season → Episode
            // ─────────────────────────────────────────────
            case 'media_id':
                $qb->orderBy('meta.mediaId', 'ASC')
                    ->addOrderBy('meta.seasonNumber', 'ASC')
                    ->addOrderBy('meta.episodeNumber', 'ASC');
                break;

            // ─────────────────────────────────────────────
            // DEFAULT SORT: newest first
            // ─────────────────────────────────────────────
            default:
                $qb->orderBy('s.createdAt', 'DESC')
                    ->addOrderBy('LOWER(meta.title)', 'ASC');
                break;
        }
    }
}
