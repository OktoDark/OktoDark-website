<?php

namespace App\Repository;

use App\Entity\LoginCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginCode>
 */
class LoginCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginCode::class);
    }

    /**
     * Find the active (non-expired) login code for a user.
     */
    public function findActiveCodeForUser(User $user): ?LoginCode
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Remove all login codes for a user (cleanup before generating a new one).
     */
    public function deleteCodesForUser(User $user): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all expired codes (optional cron cleanup).
     */
    public function deleteExpiredCodes(): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }
}
