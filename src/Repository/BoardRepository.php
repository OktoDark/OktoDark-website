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
use App\Entity\Mods; // Added Mods entity
use App\Entity\OurGames;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Board>
 */
class BoardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Board::class);
    }

    /**
     * Find boards owned or managed by a user, optionally filtered by OurGames or Mods.
     */
    public function findByUser(User $user, ?OurGames $ourGame = null, ?Mods $mod = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.members', 'm')
            ->where('b.owner = :user OR m.id = :userId')
            ->setParameter('user', $user)
            ->setParameter('userId', $user->getId());

        if ($ourGame) {
            $qb->andWhere('b.ourGame = :ourGame')
                ->setParameter('ourGame', $ourGame);
        } elseif ($mod) {
            $qb->andWhere('b.mod = :mod')
                ->setParameter('mod', $mod);
        } else {
            // If no specific game or mod is provided, only show boards not linked to any game or mod
            $qb->andWhere('b.ourGame IS NULL')
                ->andWhere('b.mod IS NULL');
        }

        return $qb->orderBy('b.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find public boards, optionally filtered by OurGames or Mods.
     */
    public function findPublic(?OurGames $ourGame = null, ?Mods $mod = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.isPublic = true');

        if ($ourGame) {
            $qb->andWhere('b.ourGame = :ourGame')
                ->setParameter('ourGame', $ourGame);
        } elseif ($mod) {
            $qb->andWhere('b.mod = :mod')
                ->setParameter('mod', $mod);
        } else {
            // If no specific game or mod is provided, only show boards not linked to any game or mod
            $qb->andWhere('b.ourGame IS NULL')
                ->andWhere('b.mod IS NULL');
        }

        return $qb->orderBy('b.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find board with all related data.
     */
    public function findBoardWithDetails(int $id): ?Board
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.columns', 'c')
            ->addSelect('c')
            ->leftJoin('c.cards', 'ca') // Join cards through columns
            ->addSelect('ca')
            ->leftJoin('ca.bugs', 'bug') // Join bugs related to cards
            ->addSelect('bug')
            ->leftJoin('ca.assignees', 'assignee') // Also fetch assignees for cards
            ->addSelect('assignee')
            ->leftJoin('assignee.assignee', 'assigneeUser') // CORRECTED: Join CardAssignee to User via 'assignee' property
            ->addSelect('assigneeUser')
            ->leftJoin('ca.createdBy', 'cardCreatedBy') // Explicitly fetch card creator
            ->addSelect('cardCreatedBy')
            ->leftJoin('b.members', 'm')
            ->addSelect('m')
            ->leftJoin('b.labels', 'l')
            ->addSelect('l')
            ->leftJoin('b.ourGame', 'og') // Add this line to join ourGame
            ->addSelect('og') // Add this line to select ourGame
            ->leftJoin('b.mod', 'mo') // Add this line to join mod
            ->addSelect('mo') // Add this line to select mod
            ->leftJoin('b.owner', 'boardOwner') // Explicitly fetch board owner
            ->addSelect('boardOwner')
            ->orderBy('c.position', 'ASC') // Order columns
            ->addOrderBy('ca.position', 'ASC') // Order cards within columns
            ->where('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find boards by owner.
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find boards associated with a specific OurGames entity.
     *
     * @return Board[] Returns an array of Board objects
     */
    public function findByOurGame(OurGames $ourGame): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.ourGame = :ourGame')
            ->setParameter('ourGame', $ourGame)
            ->orderBy('b.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find boards associated with a specific Mods entity.
     *
     * @return Board[] Returns an array of Board objects
     */
    public function findByMod(Mods $mod): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.mod = :mod')
            ->setParameter('mod', $mod)
            ->orderBy('b.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find boards based on a set of filters.
     *
     * @param array $filters An associative array of filters.
     *                       Keys can include 'ourGame', 'mod', 'isPublic', 'owner', 'member'.
     *
     * @return Board[] Returns an array of Board objects
     */
    public function findFiltered(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.owner', 'o')
            ->addSelect('o')
            ->leftJoin('b.ourGame', 'og')
            ->addSelect('og')
            ->leftJoin('b.mod', 'mo') // Added join for mod
            ->addSelect('mo') // Added select for mod
            ->orderBy('b.updatedAt', 'DESC');

        if (isset($filters['ourGame'])) {
            if ($filters['ourGame'] instanceof OurGames) {
                $qb->andWhere('b.ourGame = :ourGame')
                    ->setParameter('ourGame', $filters['ourGame']);
            } elseif (null === $filters['ourGame']) {
                $qb->andWhere('b.ourGame IS NULL');
            }
        }

        if (isset($filters['mod'])) {
            if ($filters['mod'] instanceof Mods) {
                $qb->andWhere('b.mod = :mod')
                    ->setParameter('mod', $filters['mod']);
            } elseif (null === $filters['mod']) {
                $qb->andWhere('b.mod IS NULL');
            }
        }

        // Ensure that if one is set, the other is null, or both are null for general boards
        if (isset($filters['ourGame']) && isset($filters['mod'])) {
            // This case implies a conflict or an attempt to filter by both, which isn't supported
            // For now, we'll let the first condition (ourGame) take precedence if both are set.
            // A more robust solution might involve throwing an exception or defining clear precedence.
        } elseif (isset($filters['ourGame']) && null === $filters['ourGame']) {
            // If ourGame is explicitly null, ensure mod is also null unless mod is explicitly set
            if (!isset($filters['mod'])) {
                $qb->andWhere('b.mod IS NULL');
            }
        } elseif (isset($filters['mod']) && null === $filters['mod']) {
            // If mod is explicitly null, ensure ourGame is also null unless ourGame is explicitly set
            if (!isset($filters['ourGame'])) {
                $qb->andWhere('b.ourGame IS NULL');
            }
        }

        if (isset($filters['isPublic'])) {
            $qb->andWhere('b.isPublic = :isPublic')
                ->setParameter('isPublic', (bool) $filters['isPublic']);
        }

        if (isset($filters['owner']) && $filters['owner'] instanceof User) {
            $qb->andWhere('b.owner = :owner')
                ->setParameter('owner', $filters['owner']);
        }

        if (isset($filters['member']) && $filters['member'] instanceof User) {
            $qb->leftJoin('b.members', 'm')
                ->andWhere('m = :member')
                ->setParameter('member', $filters['member']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check if user can access board.
     */
    public function canUserAccessBoard(Board $board, ?User $user): bool
    {
        if ($board->isPublic()) {
            return true;
        }

        if (null === $user) {
            return false;
        }

        return $board->getOwner() === $user || $board->isMember($user);
    }
}
