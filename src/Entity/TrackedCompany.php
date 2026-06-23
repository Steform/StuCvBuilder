<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrackedCompanyRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracked employer for CV format targeting and visit analytics.
 */
#[ORM\Entity(repositoryClass: TrackedCompanyRepository::class)]
#[ORM\Table(name: 'tracked_company', indexes: [
    new ORM\Index(name: 'idx_tracked_company_name_normalized', columns: ['name_normalized']),
    new ORM\Index(name: 'idx_tracked_company_country', columns: ['country_code']),
    new ORM\Index(name: 'idx_tracked_company_archived_at', columns: ['archived_at']),
], uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_tracked_company_code', columns: ['code']),
])]
class TrackedCompany
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 12)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'name_normalized', length: 255)]
    private string $nameNormalized;

    #[ORM\Column(name: 'country_code', length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'recruiter_name', length: 255, nullable: true)]
    private ?string $recruiterName = null;

    #[ORM\Column(name: 'address_line1', length: 255, nullable: true)]
    private ?string $addressLine1 = null;

    #[ORM\Column(name: 'address_line2', length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(name: 'address_postal_code', length: 32, nullable: true)]
    private ?string $addressPostalCode = null;

    #[ORM\Column(name: 'address_city', length: 128, nullable: true)]
    private ?string $addressCity = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToOne(targetEntity: EmploymentDocumentVariant::class)]
    #[ORM\JoinColumn(name: 'cv_document_variant_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?EmploymentDocumentVariant $cvDocumentVariant = null;

    #[ORM\ManyToOne(targetEntity: EmploymentDocumentVariant::class)]
    #[ORM\JoinColumn(name: 'lm_document_variant_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?EmploymentDocumentVariant $lmDocumentVariant = null;

    /**
     * @brief Build tracked company with generated code and normalized name.
     *
     * @param string $code Immutable 12-character company code.
     * @param string $name Display name.
     * @param string|null $countryCode ISO 3166-1 alpha-2 or null.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(string $code, string $name, ?string $countryCode = null)
    {
        $this->code = $code;
        $this->setName($name);
        $this->countryCode = $countryCode !== null && $countryCode !== '' ? strtoupper($countryCode) : null;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * @brief Get primary key.
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
     * @brief Get immutable company code used as URL format parameter.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @brief Get display name.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @brief Update display name and normalized search key.
     *
     * @param string $name New display name.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setName(string $name): self
    {
        $this->name = trim($name);
        $this->nameNormalized = self::normalizeName($this->name);
        $this->touch();

        return $this;
    }

    /**
     * @brief Get normalized name for search.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getNameNormalized(): string
    {
        return $this->nameNormalized;
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
     * @brief Set optional country code.
     *
     * @param string|null $countryCode ISO 3166-1 alpha-2 or null.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setCountryCode(?string $countryCode): self
    {
        $this->countryCode = $countryCode !== null && $countryCode !== '' ? strtoupper($countryCode) : null;
        $this->touch();

        return $this;
    }

    /**
     * @brief Get creation timestamp.
     *
     * @return DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @brief Get last update timestamp.
     *
     * @return DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @brief Get archive timestamp when set.
     *
     * @return DateTimeImmutable|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    /**
     * @brief Whether company is archived.
     *
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * @brief Archive company (soft).
     *
     * @param DateTimeImmutable $archivedAt Archive instant.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function archive(DateTimeImmutable $archivedAt): self
    {
        $this->archivedAt = $archivedAt;
        $this->touch();

        return $this;
    }

    /**
     * @brief Restore company from archived state.
     *
     * @return self
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function unarchive(): self
    {
        $this->archivedAt = null;
        $this->touch();

        return $this;
    }

    /**
     * @brief Get optional recruiter name.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getRecruiterName(): ?string
    {
        return $this->recruiterName;
    }

    /**
     * @brief Get optional address line 1.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    /**
     * @brief Get optional address line 2.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    /**
     * @brief Get optional postal code.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getAddressPostalCode(): ?string
    {
        return $this->addressPostalCode;
    }

    /**
     * @brief Get optional city.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getAddressCity(): ?string
    {
        return $this->addressCity;
    }

    /**
     * @brief Whether any structured address field is set.
     *
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function hasAddress(): bool
    {
        return $this->addressLine1 !== null
            || $this->addressLine2 !== null
            || $this->addressPostalCode !== null
            || $this->addressCity !== null;
    }

    /**
     * @brief Get optional phone number.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * @brief Get optional email address.
     *
     * @return string|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @brief Get optional linked CV document variant.
     *
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCvDocumentVariant(): ?EmploymentDocumentVariant
    {
        return $this->cvDocumentVariant;
    }

    /**
     * @brief Get optional linked cover-letter document variant.
     *
     * @return EmploymentDocumentVariant|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getLmDocumentVariant(): ?EmploymentDocumentVariant
    {
        return $this->lmDocumentVariant;
    }

    /**
     * @brief Assign optional CV and LM document variants.
     *
     * @param EmploymentDocumentVariant|null $cvDocumentVariant CV variant or null.
     * @param EmploymentDocumentVariant|null $lmDocumentVariant LM variant or null.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setDocumentVariants(
        ?EmploymentDocumentVariant $cvDocumentVariant,
        ?EmploymentDocumentVariant $lmDocumentVariant,
    ): self {
        $this->cvDocumentVariant = $cvDocumentVariant;
        $this->lmDocumentVariant = $lmDocumentVariant;
        $this->touch();

        return $this;
    }

    /**
     * @brief Apply optional recruiter contact fields.
     *
     * @param string|null $recruiterName Recruiter name or null when empty.
     * @param string|null $addressLine1 Address line 1 or null when empty.
     * @param string|null $addressLine2 Address line 2 or null when empty.
     * @param string|null $addressPostalCode Postal code or null when empty.
     * @param string|null $addressCity City or null when empty.
     * @param string|null $phone Phone or null when empty.
     * @param string|null $email Email or null when empty.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setContactDetails(
        ?string $recruiterName,
        ?string $addressLine1,
        ?string $addressLine2,
        ?string $addressPostalCode,
        ?string $addressCity,
        ?string $phone,
        ?string $email,
    ): self {
        $this->recruiterName = $recruiterName;
        $this->addressLine1 = $addressLine1;
        $this->addressLine2 = $addressLine2;
        $this->addressPostalCode = $addressPostalCode;
        $this->addressCity = $addressCity;
        $this->phone = $phone;
        $this->email = $email;
        $this->touch();

        return $this;
    }

    /**
     * @brief Bump updated_at.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @brief Normalize company name for search indexing.
     *
     * @param string $name Raw company name.
     * @return string Lowercased trimmed name.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
