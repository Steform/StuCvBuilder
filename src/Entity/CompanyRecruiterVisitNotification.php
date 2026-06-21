<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyRecruiterVisitNotificationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Daily recruiter visit notification deduplication per tracked company.
 */
#[ORM\Entity(repositoryClass: CompanyRecruiterVisitNotificationRepository::class)]
#[ORM\Table(name: 'company_recruiter_visit_notification', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_company_recruiter_notification_day', columns: ['company_id', 'notification_date']),
], indexes: [
    new ORM\Index(name: 'idx_company_recruiter_notification_visit', columns: ['visit_id']),
])]
class CompanyRecruiterVisitNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TrackedCompany::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TrackedCompany $company;

    #[ORM\Column(name: 'notification_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $notificationDate;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable')]
    private DateTimeImmutable $sentAt;

    #[ORM\ManyToOne(targetEntity: CompanyCvVisit::class)]
    #[ORM\JoinColumn(name: 'visit_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?CompanyCvVisit $visit = null;

    /**
     * @brief Build daily notification dedup row.
     *
     * @param TrackedCompany $company Tracked company.
     * @param \DateTimeImmutable $notificationDate UTC notification date.
     * @param DateTimeImmutable $sentAt Send timestamp.
     * @param CompanyCvVisit|null $visit Linked official visit when available.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(
        TrackedCompany $company,
        \DateTimeImmutable $notificationDate,
        DateTimeImmutable $sentAt,
        ?CompanyCvVisit $visit = null,
    ) {
        $this->company = $company;
        $this->notificationDate = $notificationDate;
        $this->sentAt = $sentAt;
        $this->visit = $visit;
    }

    /**
     * @brief Get row identifier.
     *
     * @param void No input parameter.
     * @return int|null
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @brief Get linked company.
     *
     * @param void No input parameter.
     * @return TrackedCompany
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getCompany(): TrackedCompany
    {
        return $this->company;
    }

    /**
     * @brief Get UTC notification date.
     *
     * @param void No input parameter.
     * @return \DateTimeImmutable
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getNotificationDate(): \DateTimeImmutable
    {
        return $this->notificationDate;
    }

    /**
     * @brief Get send timestamp.
     *
     * @param void No input parameter.
     * @return DateTimeImmutable
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    /**
     * @brief Get linked visit.
     *
     * @param void No input parameter.
     * @return CompanyCvVisit|null
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getVisit(): ?CompanyCvVisit
    {
        return $this->visit;
    }
}
