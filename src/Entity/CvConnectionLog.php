<?php

declare(strict_types=1);

namespace App\Entity;

use App\Employment\ConnectionKind;
use App\Repository\CvConnectionLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * CV access connection log (random, invalid format, or linked to official visit).
 */
#[ORM\Entity(repositoryClass: CvConnectionLogRepository::class)]
#[ORM\Table(name: 'cv_connection_log', indexes: [
    new ORM\Index(name: 'idx_cv_connection_log_occurred_at', columns: ['occurred_at']),
    new ORM\Index(name: 'idx_cv_connection_log_kind', columns: ['connection_kind']),
    new ORM\Index(name: 'idx_cv_connection_log_company', columns: ['company_id']),
])]
class CvConnectionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'connection_kind', length: 32)]
    private string $connectionKind;

    #[ORM\Column(name: 'format_raw', length: 128, nullable: true)]
    private ?string $formatRaw = null;

    #[ORM\ManyToOne(targetEntity: TrackedCompany::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TrackedCompany $company = null;

    #[ORM\Column(name: 'company_code_snapshot', length: 12, nullable: true)]
    private ?string $companyCodeSnapshot = null;

    #[ORM\Column(name: 'company_name_snapshot', length: 255, nullable: true)]
    private ?string $companyNameSnapshot = null;

    #[ORM\ManyToOne(targetEntity: CompanyCvVisit::class)]
    #[ORM\JoinColumn(name: 'visit_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?CompanyCvVisit $visit = null;

    #[ORM\Column(name: 'ip_address', length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'country_code', length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(name: 'user_agent', type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'gate_passed', type: 'boolean')]
    private bool $gatePassed = false;

    #[ORM\Column(name: 'attestation_method', length: 16, nullable: true)]
    private ?string $attestationMethod = null;

    #[ORM\Column(name: 'technical_score', type: 'integer', nullable: true)]
    private ?int $technicalScore = null;

    #[ORM\Column(name: 'countable_for_company', type: 'boolean')]
    private bool $countableForCompany = false;

    #[ORM\Column(name: 'is_admin_bypass', type: 'boolean')]
    private bool $isAdminBypass = false;

    #[ORM\Column(name: 'request_path', length: 512, nullable: true)]
    private ?string $requestPath = null;

    #[ORM\Column(name: 'request_route', length: 128, nullable: true)]
    private ?string $requestRoute = null;

    #[ORM\Column(name: 'consent_given_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $consentGivenAt = null;

    #[ORM\Column(name: 'tracking_allowed', type: 'boolean', nullable: true)]
    private ?bool $trackingAllowed = null;

    /**
     * @brief Build connection log row.
     *
     * @param string $connectionKind One of ConnectionKind constants.
     * @param DateTimeImmutable $occurredAt Event time.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(string $connectionKind, DateTimeImmutable $occurredAt)
    {
        $this->connectionKind = $connectionKind;
        $this->occurredAt = $occurredAt;
    }

    /**
     * @brief Get log identifier.
     *
     * @return int|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @brief Get occurrence time.
     *
     * @return DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @brief Get connection kind.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getConnectionKind(): string
    {
        return $this->connectionKind;
    }

    /**
     * @brief Get raw format from request.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getFormatRaw(): ?string
    {
        return $this->formatRaw;
    }

    /**
     * @brief Set raw format value.
     *
     * @param string|null $formatRaw Raw query format.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setFormatRaw(?string $formatRaw): self
    {
        $this->formatRaw = $formatRaw;

        return $this;
    }

    /**
     * @brief Get linked company when official.
     *
     * @return TrackedCompany|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCompany(): ?TrackedCompany
    {
        return $this->company;
    }

    /**
     * @brief Link official company.
     *
     * @param TrackedCompany|null $company Company entity.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setCompany(?TrackedCompany $company): self
    {
        $this->company = $company;

        return $this;
    }

    /**
     * @brief Get company code snapshot for debug rows.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCompanyCodeSnapshot(): ?string
    {
        return $this->companyCodeSnapshot;
    }

    /**
     * @brief Set company code snapshot.
     *
     * @param string|null $companyCodeSnapshot Code snapshot.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setCompanyCodeSnapshot(?string $companyCodeSnapshot): self
    {
        $this->companyCodeSnapshot = $companyCodeSnapshot;

        return $this;
    }

    /**
     * @brief Get company name snapshot.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCompanyNameSnapshot(): ?string
    {
        return $this->companyNameSnapshot;
    }

    /**
     * @brief Set company name snapshot.
     *
     * @param string|null $companyNameSnapshot Name snapshot.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setCompanyNameSnapshot(?string $companyNameSnapshot): self
    {
        $this->companyNameSnapshot = $companyNameSnapshot;

        return $this;
    }

    /**
     * @brief Get linked official visit.
     *
     * @return CompanyCvVisit|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getVisit(): ?CompanyCvVisit
    {
        return $this->visit;
    }

    /**
     * @brief Link official visit.
     *
     * @param CompanyCvVisit|null $visit Visit entity.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setVisit(?CompanyCvVisit $visit): self
    {
        $this->visit = $visit;

        return $this;
    }

    /**
     * @brief Get client IP.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * @brief Set client IP.
     *
     * @param string|null $ipAddress IP address.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /**
     * @brief Get country code.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /**
     * @brief Set country code.
     *
     * @param string|null $countryCode ISO country.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setCountryCode(?string $countryCode): self
    {
        $this->countryCode = $countryCode !== null && $countryCode !== '' ? strtoupper($countryCode) : null;

        return $this;
    }

    /**
     * @brief Get user agent.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @brief Set user agent.
     *
     * @param string|null $userAgent User-Agent header.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * @brief Whether gate was passed at log time.
     *
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isGatePassed(): bool
    {
        return $this->gatePassed;
    }

    /**
     * @brief Set gate passed flag.
     *
     * @param bool $gatePassed Gate state.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setGatePassed(bool $gatePassed): self
    {
        $this->gatePassed = $gatePassed;

        return $this;
    }

    /**
     * @brief Get attestation method.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getAttestationMethod(): ?string
    {
        return $this->attestationMethod;
    }

    /**
     * @brief Set attestation method.
     *
     * @param string|null $attestationMethod signals|captcha|null.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setAttestationMethod(?string $attestationMethod): self
    {
        $this->attestationMethod = $attestationMethod;

        return $this;
    }

    /**
     * @brief Get technical score.
     *
     * @return int|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getTechnicalScore(): ?int
    {
        return $this->technicalScore;
    }

    /**
     * @brief Set technical score.
     *
     * @param int|null $technicalScore Score 0-100.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setTechnicalScore(?int $technicalScore): self
    {
        $this->technicalScore = $technicalScore;

        return $this;
    }

    /**
     * @brief Whether row counts as official company visit.
     *
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isCountableForCompany(): bool
    {
        return $this->countableForCompany;
    }

    /**
     * @brief Set countable for company flag.
     *
     * @param bool $countableForCompany Countable flag.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setCountableForCompany(bool $countableForCompany): self
    {
        $this->countableForCompany = $countableForCompany;

        return $this;
    }

    /**
     * @brief Whether admin bypass was active.
     *
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isAdminBypass(): bool
    {
        return $this->isAdminBypass;
    }

    /**
     * @brief Set admin bypass flag.
     *
     * @param bool $isAdminBypass Admin bypass state.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setIsAdminBypass(bool $isAdminBypass): self
    {
        $this->isAdminBypass = $isAdminBypass;

        return $this;
    }

    /**
     * @brief Get request path.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getRequestPath(): ?string
    {
        return $this->requestPath;
    }

    /**
     * @brief Set request path.
     *
     * @param string|null $requestPath Path.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setRequestPath(?string $requestPath): self
    {
        $this->requestPath = $requestPath;

        return $this;
    }

    /**
     * @brief Get request route name.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getRequestRoute(): ?string
    {
        return $this->requestRoute;
    }

    /**
     * @brief Set request route name.
     *
     * @param string|null $requestRoute Route name.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setRequestRoute(?string $requestRoute): self
    {
        $this->requestRoute = $requestRoute;

        return $this;
    }
}
