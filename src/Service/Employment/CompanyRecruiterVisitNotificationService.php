<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\CompanyCvVisit;
use App\Entity\CompanyRecruiterVisitNotification;
use App\Entity\TrackedCompany;
use App\Repository\CompanyRecruiterVisitNotificationRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists daily recruiter visit notification deduplication rows.
 */
final class CompanyRecruiterVisitNotificationService
{
    /**
     * @brief Build recruiter visit notification dedup service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param CompanyRecruiterVisitNotificationRepository $notificationRepository Notification repository.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyRecruiterVisitNotificationRepository $notificationRepository,
    ) {
    }

    /**
     * @brief Claim one notification slot for company and UTC day when not already sent.
     *
     * @param TrackedCompany $company Tracked company.
     * @param \DateTimeImmutable $notificationDate UTC notification date.
     * @param CompanyCvVisit|null $visit Linked official visit.
     * @return bool True when this call claimed the daily slot.
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function tryClaimDailyNotification(
        TrackedCompany $company,
        \DateTimeImmutable $notificationDate,
        ?CompanyCvVisit $visit = null,
    ): bool {
        $existing = $this->notificationRepository->findOneForDay($company, $notificationDate);
        if ($existing instanceof CompanyRecruiterVisitNotification) {
            return false;
        }

        $row = new CompanyRecruiterVisitNotification(
            $company,
            $notificationDate,
            new DateTimeImmutable(),
            $visit,
        );
        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return true;
    }
}
