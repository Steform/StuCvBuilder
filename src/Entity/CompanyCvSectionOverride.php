<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyCvSectionOverrideRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-company override payload for one CV web section.
 */
#[ORM\Entity(repositoryClass: CompanyCvSectionOverrideRepository::class)]
#[ORM\Table(name: 'company_cv_section_override', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_company_cv_override_section', columns: ['tracked_company_id', 'section_key']),
])]
class CompanyCvSectionOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TrackedCompany::class)]
    #[ORM\JoinColumn(name: 'tracked_company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TrackedCompany $trackedCompany;

    #[ORM\Column(name: 'section_key', length: 32)]
    private string $sectionKey;

    #[ORM\Column(name: 'content_json', type: 'text')]
    private string $contentJson;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @brief Build company section override row.
     *
     * @param TrackedCompany $trackedCompany Owning company.
     * @param string $sectionKey Section slug (e.g. `about`).
     * @param string $contentJson JSON payload for the section.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(TrackedCompany $trackedCompany, string $sectionKey, string $contentJson)
    {
        $this->trackedCompany = $trackedCompany;
        $this->sectionKey = $sectionKey;
        $this->contentJson = $contentJson;
        $this->touchUpdatedAt();
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
     * @brief Get owning company.
     *
     * @return TrackedCompany
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getTrackedCompany(): TrackedCompany
    {
        return $this->trackedCompany;
    }

    /**
     * @brief Get section key.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getSectionKey(): string
    {
        return $this->sectionKey;
    }

    /**
     * @brief Get stored JSON payload.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getContentJson(): string
    {
        return $this->contentJson;
    }

    /**
     * @brief Replace stored JSON payload.
     *
     * @param string $contentJson JSON payload.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setContentJson(string $contentJson): self
    {
        $this->contentJson = $contentJson;
        $this->touchUpdatedAt();

        return $this;
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
     * @brief Refresh updated-at timestamp.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function touchUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
