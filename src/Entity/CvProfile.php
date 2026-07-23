<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class CvProfile.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\CvProfileRepository')]
#[ORM\Table(name: 'cv_profile')]
#[ORM\UniqueConstraint(name: 'uniq_cv_profile_tracked_company', columns: ['tracked_company_id'])]
class CvProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $contentJson;

    #[ORM\ManyToOne(targetEntity: TrackedCompany::class)]
    #[ORM\JoinColumn(name: 'tracked_company_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?TrackedCompany $trackedCompany = null;

    /**
     * @brief Build default CV profile.
     * @param string $title Profile title.
     * @param string $contentJson JSON content payload.
     * @return void
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function __construct(string $title, string $contentJson)
    {
        $this->title = $title;
        $this->contentJson = $contentJson;
    }

    /**
     * @brief Get profile identifier.
     * @return int|null
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @brief Get profile title.
     * @return string
     * @date 2026-04-24
     * @author Stephane H.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @brief Update profile title.
     * @param string $title Profile title.
     * @return self
     * @date 2026-04-24
     * @author Stephane H.
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @brief Get profile content payload.
     * @return string
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function getContentJson(): string
    {
        return $this->contentJson;
    }

    /**
     * @brief Update profile content payload.
     * @param string $contentJson JSON content payload.
     * @return self
     * @date 2026-04-24
     * @author Stephane H.
     */
    public function setContentJson(string $contentJson): self
    {
        $this->contentJson = $contentJson;

        return $this;
    }

    /**
     * @brief Get owning tracked company when this profile is a company clone.
     *
     * @return TrackedCompany|null Null for the site-wide global profile.
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function getTrackedCompany(): ?TrackedCompany
    {
        return $this->trackedCompany;
    }

    /**
     * @brief Bind this profile to a tracked company (company clone) or clear for global.
     *
     * @param TrackedCompany|null $trackedCompany Owning company or null for global.
     * @return self
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function setTrackedCompany(?TrackedCompany $trackedCompany): self
    {
        $this->trackedCompany = $trackedCompany;

        return $this;
    }

    /**
     * @brief Whether this row is the site-wide global CV profile.
     *
     * @return bool
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function isGlobal(): bool
    {
        return $this->trackedCompany === null;
    }
}
