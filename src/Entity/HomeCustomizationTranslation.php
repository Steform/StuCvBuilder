<?php

namespace App\Entity;

use App\Repository\HomeCustomizationTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Localized intro text for home customization.
 */
#[ORM\Entity(repositoryClass: HomeCustomizationTranslationRepository::class)]
#[ORM\Table(name: 'home_customization_translation')]
#[ORM\UniqueConstraint(name: 'uniq_home_customization_locale', columns: ['customization_id', 'locale'])]
class HomeCustomizationTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomeCustomization::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HomeCustomization $customization = null;

    #[ORM\Column(length: 8)]
    private string $locale = '';

    #[ORM\Column(type: 'text')]
    private string $introText = '';

    #[ORM\Column(length: 512, options: ['default' => ''])]
    private string $metaDescription = '';

    /**
     * @brief Get primary key.
     * @return int|null
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @brief Get parent customization.
     * @return HomeCustomization|null
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function getCustomization(): ?HomeCustomization
    {
        return $this->customization;
    }

    /**
     * @brief Set parent customization.
     * @param HomeCustomization|null $customization Parent customization.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function setCustomization(?HomeCustomization $customization): void
    {
        $this->customization = $customization;
    }

    /**
     * @brief Get locale code.
     * @return string
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @brief Set locale code.
     * @param string $locale Locale code.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @brief Get localized intro text.
     * @return string
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function getIntroText(): string
    {
        return $this->introText;
    }

    /**
     * @brief Set localized intro text.
     * @param string $introText Intro copy.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function setIntroText(string $introText): void
    {
        $this->introText = $introText;
    }

    /**
     * @brief Get localized SEO meta description override.
     *
     * @return string Plain-text meta description or empty string when unset.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    /**
     * @brief Set localized SEO meta description override.
     *
     * @param string $metaDescription Sanitized meta description text.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function setMetaDescription(string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }
}
