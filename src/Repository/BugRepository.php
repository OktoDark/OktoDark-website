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

use App\Entity\Board;
use App\Entity\Bug;
use App\Entity\Card;
use App\Entity\Mods; // Added Mods entity
use App\Entity\OurGames; // Added OurGames entity
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bug>
 */
class BugRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bug::class);
    }

    /**
     * Find bugs by status.
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', $status)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find bugs by severity.
     */
    public function findBySeverity(string $severity): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.severity = :severity')
            ->setParameter('severity', $severity)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find unresolved bugs.
     */
    public function findUnresolved(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.status IN (:statuses)')
            ->setParameter('statuses', [Bug::STATUS_OPEN, Bug::STATUS_IN_PROGRESS])
            ->orderBy('b.severity', 'DESC')
            ->addOrderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find bugs assigned to user.
     */
    public function findByAssignee(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.assignee = :user')
            ->setParameter('user', $user)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find bugs reported by user.
     */
    public function findByReporter(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.reporter = :user')
            ->setParameter('user', $user)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find critical bugs.
     */
    public function findCritical(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.severity = :severity AND b.status != :closedStatus')
            ->setParameter('severity', Bug::SEVERITY_CRITICAL)
            ->setParameter('closedStatus', Bug::STATUS_CLOSED)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find bugs with details.
     */
    public function findBugWithDetails(int $id): ?Bug
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.reporter', 'r')
            ->leftJoin('b.assignee', 'a')
            ->leftJoin('b.kanbanCard', 'c')
            ->leftJoin('b.ourGame', 'og') // Added join for ourGame
            ->leftJoin('b.mod', 'mo') // Added join for mod
            ->addSelect('r', 'a', 'c', 'og', 'mo') // Added ourGame and mod to select
            ->where('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find bugs associated with a specific Board.
     * This works by joining Bug -> Card -> Board.
     *
     * @return Bug[] Returns an array of Bug objects
     */
    public function findByBoard(Board $board): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.kanbanCard', 'c') // Join Bug with Card
            ->join('c.board', 'board') // Join Card with Board
            ->where('board = :board') // Filter by the specific Board
            ->setParameter('board', $board)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find bugs based on a set of filters.
     *
     * @param array $filters An associative array of filters.
     *                       Keys can include 'board', 'status', 'severity', 'assignee', 'cardFilter', 'ourGame', 'mod'.
     *
     * @return Bug[] Returns an array of Bug objects
     */
    public function findFiltered(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.reporter', 'r')
            ->leftJoin('b.assignee', 'a')
            ->leftJoin('b.kanbanCard', 'c')
            ->leftJoin('b.ourGame', 'og') // Added join for ourGame
            ->leftJoin('b.mod', 'mo') // Added join for mod
            ->addSelect('r', 'a', 'c', 'og', 'mo') // Added ourGame and mod to select
            ->orderBy('b.reportedAt', 'DESC');

        // Apply ourGame filter
        if (isset($filters['ourGame'])) {
            if ($filters['ourGame'] instanceof OurGames) {
                $qb->andWhere('b.ourGame = :ourGame')
                    ->setParameter('ourGame', $filters['ourGame']);
            } elseif (null === $filters['ourGame']) {
                $qb->andWhere('b.ourGame IS NULL');
            }
        }

        // Apply mod filter
        if (isset($filters['mod'])) {
            if ($filters['mod'] instanceof Mods) {
                $qb->andWhere('b.mod = :mod')
                    ->setParameter('mod', $filters['mod']);
            } elseif (null === $filters['mod']) {
                $qb->andWhere('b.mod IS NULL');
            }
        }

        if (isset($filters['board']) && $filters['board'] instanceof Board) {
            // Add a condition that the bug's kanbanCard's board must match the filter board.
            // This implicitly filters out bugs without a kanbanCard when a board filter is active.
            $qb->andWhere('c.board = :board')
                ->setParameter('board', $filters['board']);
        }

        if (isset($filters['status'])) {
            $qb->andWhere('b.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['severity'])) {
            $qb->andWhere('b.severity = :severity')
                ->setParameter('severity', $filters['severity']);
        }

        if (isset($filters['assignee']) && $filters['assignee'] instanceof User) {
            $qb->andWhere('b.assignee = :assignee')
                ->setParameter('assignee', $filters['assignee']);
        }

        if (isset($filters['cardFilter'])) {
            if (true === $filters['cardFilter']) {
                // Filter bugs that have linked cards
                $qb->andWhere('b.kanbanCard IS NOT NULL');
            } elseif (false === $filters['cardFilter']) {
                // Filter bugs that don't have linked cards
                $qb->andWhere('b.kanbanCard IS NULL');
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find bugs linked to a specific Kanban card.
     */
    public function findByKanbanCard(Card $card): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.kanbanCard = :card')
            ->setParameter('card', $card)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find bugs associated with a specific OurGames entity.
     *
     * @return Bug[] Returns an array of Bug objects
     */
    public function findByOurGame(OurGames $ourGame): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.ourGame = :ourGame')
            ->setParameter('ourGame', $ourGame)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bugs associated with a specific Mods entity.
     *
     * @return Bug[] Returns an array of Bug objects
     */
    public function findByMod(Mods $mod): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.mod = :mod')
            ->setParameter('mod', $mod)
            ->orderBy('b.reportedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
