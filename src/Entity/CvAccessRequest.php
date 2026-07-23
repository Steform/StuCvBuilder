<?php

declare(strict_types=1);

namespace App\Entity;

use App\Cv\CvAccessRequestStatus;
use App\Repository\CvAccessRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @brief Public recruiter request to create a tracked company and obtain CV access.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
#[ORM\Entity(repositoryClass: CvAccessRequestRepository::class)]
#[ORM\Table(name: 'cv_access_request')]
#[ORM\Index(name: 'idx_cv_access_request_status', columns: ['status'])]
#[ORM\Index(name: 'idx_cv_access_request_created_at', columns: ['created_at'])]
class CvAccessRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'company_name', length: 255)]
    private string $companyName;

    #[ORM\Column(name: 'recruiter_name', length: 255)]
    private string $recruiterName;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(name: 'country_code', length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 32)]
    private string $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'reviewed_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(name: 'reviewer_note', type: 'text', nullable: true)]
    private ?string $reviewerNote = null;

    #[ORM\ManyToOne(targetEntity: TrackedCompany::class)]
    #[ORM\JoinColumn(name: 'tracked_company_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TrackedCompany $trackedCompany = null;

    #[ORM\Column(name: 'submitter_ip', length: 45, nullable: true)]
    private ?string $submitterIp = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $locale = null;

    /**
     * @brief Build a pending access request.
     *
     * @param string $companyName Company display name.
     * @param string $recruiterName Recruiter display name.
     * @param string $email Recruiter email.
     * @param string|null $countryCode Optional ISO country code.
     * @param string|null $message Optional free-text message.
     * @param string|null $submitterIp Optional submitter IP.
     * @param string|null $locale Optional visitor locale.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function __construct(
        string $companyName,
        string $recruiterName,
        string $email,
        ?string $countryCode = null,
        ?string $message = null,
        ?string $submitterIp = null,
        ?string $locale = null,
    ) {
        $this->companyName = $companyName;
        $this->recruiterName = $recruiterName;
        $this->email = $email;
        $this->countryCode = $countryCode;
        $this->message = $message;
        $this->submitterIp = $submitterIp;
        $this->locale = $locale;
        $this->status = CvAccessRequestStatus::Pending->value;
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * @brief Get primary key.
     *
     * @return int|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @brief Get company display name.
     *
     * @return string
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    /**
     * @brief Get recruiter display name.
     *
     * @return string
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getRecruiterName(): string
    {
        return $this->recruiterName;
    }

    /**
     * @brief Get recruiter email.
     *
     * @return string
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @brief Get optional country code.
     *
     * @return string|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /**
     * @brief Get optional message.
     *
     * @return string|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @brief Get workflow status.
     *
     * @return CvAccessRequestStatus
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getStatus(): CvAccessRequestStatus
    {
        return CvAccessRequestStatus::from($this->status);
    }

    /**
     * @brief Whether the request is still pending review.
     *
     * @return bool
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function isPending(): bool
    {
        return $this->getStatus() === CvAccessRequestStatus::Pending;
    }

    /**
     * @brief Get creation timestamp.
     *
     * @return DateTimeImmutable
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @brief Get review timestamp.
     *
     * @return DateTimeImmutable|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    /**
     * @brief Get optional reviewer note.
     *
     * @return string|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getReviewerNote(): ?string
    {
        return $this->reviewerNote;
    }

    /**
     * @brief Get linked tracked company when approved.
     *
     * @return TrackedCompany|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getTrackedCompany(): ?TrackedCompany
    {
        return $this->trackedCompany;
    }

    /**
     * @brief Get submitter IP.
     *
     * @return string|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getSubmitterIp(): ?string
    {
        return $this->submitterIp;
    }

    /**
     * @brief Get visitor locale.
     *
     * @return string|null
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @brief Mark request as approved and link the created company.
     *
     * @param TrackedCompany $company Created tracked company.
     * @param string|null $reviewerNote Optional admin note.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function approve(TrackedCompany $company, ?string $reviewerNote = null): void
    {
        $this->status = CvAccessRequestStatus::Approved->value;
        $this->trackedCompany = $company;
        $this->reviewedAt = new DateTimeImmutable();
        $this->reviewerNote = $reviewerNote !== null && trim($reviewerNote) !== '' ? trim($reviewerNote) : null;
    }

    /**
     * @brief Mark request as rejected.
     *
     * @param string|null $reviewerNote Optional admin note.
     * @return void
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function reject(?string $reviewerNote = null): void
    {
        $this->status = CvAccessRequestStatus::Rejected->value;
        $this->reviewedAt = new DateTimeImmutable();
        $this->reviewerNote = $reviewerNote !== null && trim($reviewerNote) !== '' ? trim($reviewerNote) : null;
    }
}
