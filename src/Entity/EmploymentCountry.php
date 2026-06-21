<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmploymentCountryRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Admin-managed country option for tracked companies.
 */
#[ORM\Entity(repositoryClass: EmploymentCountryRepository::class)]
#[ORM\Table(name: 'employment_country')]
#[ORM\UniqueConstraint(name: 'uniq_employment_country_code', columns: ['code'])]
class EmploymentCountry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 5)]
    private string $presentationLocale;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @brief Create employment country row.
     *
     * @param string $code ISO 3166-1 alpha-2 code.
     * @param string $label Display label for admin UI.
     * @param string $presentationLocale Active site locale for CV/LM and public pages.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(string $code, string $label, string $presentationLocale)
    {
        $this->code = strtoupper(trim($code));
        $this->label = trim($label);
        $this->presentationLocale = strtolower(trim($presentationLocale));
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /**
     * @brief Return internal id.
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
     * @brief Return ISO country code.
     *
     * @param void No input parameter.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @brief Return admin display label.
     *
     * @param void No input parameter.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @brief Update display label.
     *
     * @param string $label New label.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setLabel(string $label): self
    {
        $this->label = trim($label);
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    /**
     * @brief Return locale used for CV, cover letter, and site when this country applies.
     *
     * @param void No input parameter.
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getPresentationLocale(): string
    {
        return $this->presentationLocale;
    }

    /**
     * @brief Set presentation locale for CV/LM and site.
     *
     * @param string $presentationLocale Active locale code.
     * @return self
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function setPresentationLocale(string $presentationLocale): self
    {
        $this->presentationLocale = strtolower(trim($presentationLocale));
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    /**
     * @brief Return creation timestamp.
     *
     * @param void No input parameter.
     * @return DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @brief Return last update timestamp.
     *
     * @param void No input parameter.
     * @return DateTimeImmutable
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
