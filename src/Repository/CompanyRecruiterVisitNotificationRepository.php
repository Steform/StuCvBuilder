<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompanyRecruiterVisitNotification;
use App\Entity\TrackedCompany;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyRecruiterVisitNotification>
 */
class CompanyRecruiterVisitNotificationRepository extends ServiceEntityRepository
{
    /**
     * @brief Build repository.
     *
     * @param ManagerRegistry $registry Doctrine registry.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyRecruiterVisitNotification::class);
    }

    /**
     * @brief Find notification row for company and UTC day.
     *
     * @param TrackedCompany $company Tracked company.
     * @param \DateTimeImmutable $notificationDate UTC date.
     * @return CompanyRecruiterVisitNotification|null
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function findOneForDay(TrackedCompany $company, \DateTimeImmutable $notificationDate): ?CompanyRecruiterVisitNotification
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.company = :company')
            ->andWhere('n.notificationDate = :notificationDate')
            ->setParameter('company', $company)
            ->setParameter('notificationDate', $notificationDate->format('Y-m-d'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
