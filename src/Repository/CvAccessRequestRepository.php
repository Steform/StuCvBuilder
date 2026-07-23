<?php

declare(strict_types=1);

namespace App\Repository;

use App\Cv\CvAccessRequestStatus;
use App\Entity\CvAccessRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @brief Doctrine repository for {@see CvAccessRequest}.
 *
 * @extends ServiceEntityRepository<CvAccessRequest>
 * @date 2026-07-22
 * @author Stephane H.
 */
class CvAccessRequestRepository extends ServiceEntityRepository
{
    /**
     * @brief Build repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvAccessRequest::class);
    }

    /**
     * @brief List access requests for admin UI, optionally filtered by status.
     *
     * @param string|null $statusFilter Status slug or empty for all.
     * @return list<CvAccessRequest>
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function findForAdminList(?string $statusFilter): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        $normalized = strtolower(trim((string) $statusFilter));
        if ($normalized !== '') {
            $status = CvAccessRequestStatus::tryFrom($normalized);
            if ($status !== null) {
                $qb->andWhere('r.status = :status')
                    ->setParameter('status', $status->value);
            }
        }

        /** @var list<CvAccessRequest> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * @brief Count pending access requests.
     *
     * @return int
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', CvAccessRequestStatus::Pending->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
