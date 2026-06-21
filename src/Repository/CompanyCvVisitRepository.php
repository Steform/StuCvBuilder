<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompanyCvVisit;
use App\Entity\TrackedCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyCvVisit>
 */
class CompanyCvVisitRepository extends ServiceEntityRepository
{
    /**
     * @brief Build company CV visit repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyCvVisit::class);
    }

    /**
     * @brief Find visit for company, UTC day, and visitor key.
     *
     * @param TrackedCompany $company Company.
     * @param \DateTimeImmutable $visitDate UTC date only.
     * @param string $visitorKey Visitor deduplication key.
     * @return CompanyCvVisit|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findOneForDay(
        TrackedCompany $company,
        \DateTimeImmutable $visitDate,
        string $visitorKey,
    ): ?CompanyCvVisit {
        return $this->createQueryBuilder('v')
            ->andWhere('v.company = :company')
            ->andWhere('v.visitDate = :visitDate')
            ->andWhere('v.visitorKey = :visitorKey')
            ->setParameter('company', $company)
            ->setParameter('visitDate', $visitDate)
            ->setParameter('visitorKey', $visitorKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @brief List official visits for company fiche.
     *
     * @param TrackedCompany $company Company.
     * @param int $limit Max rows.
     * @return list<CompanyCvVisit>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function findForCompanyShow(TrackedCompany $company, int $limit = 100): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.company = :company')
            ->setParameter('company', $company)
            ->orderBy('v.visitDate', 'DESC')
            ->addOrderBy('v.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
