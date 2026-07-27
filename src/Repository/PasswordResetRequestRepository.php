<?php

namespace App\Repository;

use App\Entity\PasswordResetRequest;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class PasswordResetRequestRepository.
 *
 * @extends ServiceEntityRepository<PasswordResetRequest>
 */
class PasswordResetRequestRepository extends ServiceEntityRepository
{
    /**
     * @brief Build password reset request repository.
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetRequest::class);
    }

    /**
     * @brief Find active password reset request by token hash.
     * @param string $tokenHash Hashed reset token.
     * @param DateTimeImmutable $now Current datetime.
     * @return PasswordResetRequest|null
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function findActiveByTokenHash(string $tokenHash, DateTimeImmutable $now): ?PasswordResetRequest
    {
        return $this->createQueryBuilder('reset_request')
            ->andWhere('reset_request.token = :tokenHash')
            ->andWhere('reset_request.consumed = :consumed')
            ->andWhere('reset_request.expiresAt >= :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('consumed', false)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief Consume all active reset requests for one user.
     * @param int $userId Target user identifier.
     * @return void
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function invalidateActiveForUser(int $userId): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->update(PasswordResetRequest::class, 'reset_request')
            ->set('reset_request.consumed', ':consumed')
            ->where('reset_request.userId = :userId')
            ->andWhere('reset_request.consumed = :active')
            ->setParameter('consumed', true)
            ->setParameter('userId', $userId)
            ->setParameter('active', false)
            ->getQuery()
            ->execute();
    }
}
