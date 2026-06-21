<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyCvVisitRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Official recruiter CV visit aggregated per company per UTC day.
 */
#[ORM\Entity(repositoryClass: CompanyCvVisitRepository::class)]
#[ORM\Table(name: 'company_cv_visit', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_company_visit_day_visitor', columns: ['company_id', 'visit_date', 'visitor_key']),
], indexes: [
    new ORM\Index(name: 'idx_company_cv_visit_company_date', columns: ['company_id', 'visit_date']),
])]
class CompanyCvVisit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TrackedCompany::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TrackedCompany $company;

    #[ORM\Column(name: 'visit_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $visitDate;

    #[ORM\Column(name: 'visitor_key', length: 64)]
    private string $visitorKey;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable')]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'last_activity_at', type: 'datetime_immutable')]
    private DateTimeImmutable $lastActivityAt;

    /**
     * @var list<array{route: string, path: string, viewedAt: string}>
     */
    #[ORM\Column(name: 'journey_json', type: 'json')]
    private array $journeyJson = [];

    #[ORM\Column(name: 'ip_address', length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'country_code', length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(name: 'consent_given_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $consentGivenAt = null;

    #[ORM\Column(name: 'tracking_allowed', type: 'boolean', nullable: true)]
    private ?bool $trackingAllowed = null;

    /**
     * @brief Build official company visit for a UTC day.
     *
     * @param TrackedCompany $company Tracked company.
     * @param \DateTimeImmutable $visitDate UTC visit date (date only).
     * @param string $visitorKey Stable visitor deduplication key.
     * @param DateTimeImmutable $startedAt First activity instant.
     * @param string|null $ipAddress Client IP snapshot.
     * @param string|null $countryCode Geo country snapshot.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        TrackedCompany $company,
        \DateTimeImmutable $visitDate,
        string $visitorKey,
        DateTimeImmutable $startedAt,
        ?string $ipAddress = null,
        ?string $countryCode = null,
    ) {
        $this->company = $company;
        $this->visitDate = $visitDate;
        $this->visitorKey = $visitorKey;
        $this->startedAt = $startedAt;
        $this->lastActivityAt = $startedAt;
        $this->ipAddress = $ipAddress;
        $this->countryCode = $countryCode !== null && $countryCode !== '' ? strtoupper($countryCode) : null;
    }

    /**
     * @brief Get visit identifier.
     *
     * @param void No input parameter.
     * @return int|null
     * @date 2026-06-01
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
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCompany(): TrackedCompany
    {
        return $this->company;
    }

    /**
     * @brief Get UTC visit date.
     *
     * @param void No input parameter.
     * @return \DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getVisitDate(): \DateTimeImmutable
    {
        return $this->visitDate;
    }

    /**
     * @brief Get visitor deduplication key.
     *
     * @param void No input parameter.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getVisitorKey(): string
    {
        return $this->visitorKey;
    }

    /**
     * @brief Get visit start time.
     *
     * @param void No input parameter.
     * @return DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * @brief Get last journey activity time.
     *
     * @param void No input parameter.
     * @return DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getLastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    /**
     * @brief Get ordered journey steps.
     *
     * @return list<array{route: string, path: string, viewedAt: string}>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getJourneyJson(): array
    {
        return $this->journeyJson;
    }

    /**
     * @brief Append a journey step when not duplicate of the last step.
     *
     * @param string $route Symfony route name.
     * @param string $path Request path.
     * @param DateTimeImmutable $viewedAt View timestamp.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function appendJourneyStep(string $route, string $path, DateTimeImmutable $viewedAt): self
    {
        $step = [
            'route' => $route,
            'path' => $path,
            'viewedAt' => $viewedAt->format(DateTimeImmutable::ATOM),
        ];
        $last = $this->journeyJson !== [] ? $this->journeyJson[array_key_last($this->journeyJson)] : null;
        if (is_array($last) && ($last['route'] ?? '') === $route && ($last['path'] ?? '') === $path) {
            $this->lastActivityAt = $viewedAt;

            return $this;
        }

        $this->journeyJson[] = $step;
        $this->lastActivityAt = $viewedAt;

        return $this;
    }

    /**
     * @brief Get stored IP address.
     *
     * @param void No input parameter.
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * @brief Get stored country code.
     *
     * @param void No input parameter.
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }
}
