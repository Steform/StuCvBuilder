<?php

namespace App\Entity;

use App\Repository\HomeCustomizationRepository;
use App\Service\Home\HomeQuickTilePresetRegistry;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Global singleton configuration for public home landing customization.
 */
#[ORM\Entity(repositoryClass: HomeCustomizationRepository::class)]
#[ORM\Table(name: 'home_customization')]
class HomeCustomization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $signatureImageRelativePath = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $backgroundImageRelativePath = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $backgroundCssSanitized = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureCssSanitized = null;

    #[ORM\Column(length: 32)]
    private string $quickTileStyle = 'style_1';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $quickTileCssSanitized = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $dashboardTileIconRelativePath = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $siteFaviconRelativePath = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $openGraphImageRelativePath = null;

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private int $cvAntibotThreshold = 50;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $maintenanceModeEnabled = false;

    #[ORM\Column(name: 'recruiter_visit_notification_enabled', type: 'boolean', options: ['default' => false])]
    private bool $recruiterVisitNotificationEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $siteColorsJson = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $mailTemplatesJson = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $introTitleCssSanitized = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $webcvButtonCssSanitized = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $webcvButtonCssHoverSanitized = null;

    /**
     * @var Collection<int, HomeCustomizationTranslation>
     */
    #[ORM\OneToMany(targetEntity: HomeCustomizationTranslation::class, mappedBy: 'customization', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /**
     * @var Collection<int, HomeQuickTile>
     */
    #[ORM\OneToMany(targetEntity: HomeQuickTile::class, mappedBy: 'customization', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $quickTiles;

    /**
     * @brief Build home customization aggregate.
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->quickTiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSignatureImageRelativePath(): ?string
    {
        return $this->signatureImageRelativePath;
    }

    public function setSignatureImageRelativePath(?string $signatureImageRelativePath): void
    {
        $this->signatureImageRelativePath = $signatureImageRelativePath;
    }

    public function getBackgroundImageRelativePath(): ?string
    {
        return $this->backgroundImageRelativePath;
    }

    public function setBackgroundImageRelativePath(?string $backgroundImageRelativePath): void
    {
        $this->backgroundImageRelativePath = $backgroundImageRelativePath;
    }

    /**
     * @brief Get sanitized CSS declarations for the landing background container.
     * @param void No input parameter.
     * @return string|null
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getBackgroundCssSanitized(): ?string
    {
        return $this->backgroundCssSanitized;
    }

    /**
     * @brief Set sanitized CSS declarations for the landing background container.
     * @param string|null $backgroundCssSanitized Sanitized CSS declarations.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function setBackgroundCssSanitized(?string $backgroundCssSanitized): void
    {
        $this->backgroundCssSanitized = $backgroundCssSanitized;
    }

    /**
     * @brief Get sanitized CSS declarations for the signature image element.
     * @param void No input parameter.
     * @return string|null
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getSignatureCssSanitized(): ?string
    {
        return $this->signatureCssSanitized;
    }

    /**
     * @brief Set sanitized CSS declarations for the signature image element.
     * @param string|null $signatureCssSanitized Sanitized CSS declarations.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function setSignatureCssSanitized(?string $signatureCssSanitized): void
    {
        $this->signatureCssSanitized = $signatureCssSanitized;
    }

    /**
     * @brief Get selected quick tile preset key or custom mode.
     * @param void No input parameter.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getQuickTileStyle(): string
    {
        return $this->quickTileStyle;
    }

    /**
     * @brief Set selected quick tile preset key or custom mode.
     * @param string $quickTileStyle Preset key or {@see HomeQuickTilePresetRegistry::STYLE_CUSTOM}.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function setQuickTileStyle(string $quickTileStyle): void
    {
        $this->quickTileStyle = $quickTileStyle;
    }

    /**
     * @brief Get custom quick tile CSS when style is custom.
     * @param void No input parameter.
     * @return string|null
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getQuickTileCssSanitized(): ?string
    {
        return $this->quickTileCssSanitized;
    }

    /**
     * @brief Set custom quick tile CSS declarations.
     * @param string|null $quickTileCssSanitized Sanitized CSS declarations.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function setQuickTileCssSanitized(?string $quickTileCssSanitized): void
    {
        $this->quickTileCssSanitized = $quickTileCssSanitized;
    }

    /**
     * @brief Get optional dashboard quick tile icon path relative to public/.
     * @param void No input parameter.
     * @return string|null
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getDashboardTileIconRelativePath(): ?string
    {
        return $this->dashboardTileIconRelativePath;
    }

    /**
     * @brief Set optional dashboard quick tile icon path relative to public/.
     * @param string|null $dashboardTileIconRelativePath Relative asset path.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function setDashboardTileIconRelativePath(?string $dashboardTileIconRelativePath): void
    {
        $this->dashboardTileIconRelativePath = $dashboardTileIconRelativePath;
    }

    /**
     * @brief Get optional custom site favicon path relative to public/.
     *
     * @param void No input parameter.
     * @return string|null Relative asset path when a custom favicon is stored.
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function getSiteFaviconRelativePath(): ?string
    {
        return $this->siteFaviconRelativePath;
    }

    /**
     * @brief Set optional custom site favicon path relative to public/.
     *
     * @param string|null $siteFaviconRelativePath Relative asset path or null for system default.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function setSiteFaviconRelativePath(?string $siteFaviconRelativePath): void
    {
        $this->siteFaviconRelativePath = $siteFaviconRelativePath;
    }

    /**
     * @brief Get optional dedicated Open Graph image path relative to public/.
     *
     * @param void No input parameter.
     * @return string|null Relative asset path when a custom OG image is stored.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function getOpenGraphImageRelativePath(): ?string
    {
        return $this->openGraphImageRelativePath;
    }

    /**
     * @brief Set optional dedicated Open Graph image path relative to public/.
     *
     * @param string|null $openGraphImageRelativePath Relative asset path or null when unset.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function setOpenGraphImageRelativePath(?string $openGraphImageRelativePath): void
    {
        $this->openGraphImageRelativePath = $openGraphImageRelativePath;
    }

    /**
     * @brief Get minimum technical score required for CV public access without captcha.
     *
     * @param void No input parameter.
     * @return int Threshold between 0 and 100.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getCvAntibotThreshold(): int
    {
        return $this->cvAntibotThreshold;
    }

    /**
     * @brief Set minimum technical score required for CV public access without captcha.
     *
     * @param int $cvAntibotThreshold Threshold between 0 and 100.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function setCvAntibotThreshold(int $cvAntibotThreshold): void
    {
        $this->cvAntibotThreshold = max(0, min(100, $cvAntibotThreshold));
    }

    /**
     * @brief Return whether public home and CV routes are replaced by a maintenance page.
     *
     * @param void No input parameter.
     * @return bool True when maintenance mode is active.
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function isMaintenanceModeEnabled(): bool
    {
        return $this->maintenanceModeEnabled;
    }

    /**
     * @brief Enable or disable public maintenance mode for home and CV routes.
     *
     * @param bool $maintenanceModeEnabled Maintenance toggle state.
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function setMaintenanceModeEnabled(bool $maintenanceModeEnabled): void
    {
        $this->maintenanceModeEnabled = $maintenanceModeEnabled;
    }

    /**
     * @brief Return whether recruiter visit email notifications are enabled.
     *
     * @param void No input parameter.
     * @return bool True when notifications should be sent.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function isRecruiterVisitNotificationEnabled(): bool
    {
        return $this->recruiterVisitNotificationEnabled;
    }

    /**
     * @brief Enable or disable recruiter visit email notifications.
     *
     * @param bool $recruiterVisitNotificationEnabled Notification toggle state.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function setRecruiterVisitNotificationEnabled(bool $recruiterVisitNotificationEnabled): void
    {
        $this->recruiterVisitNotificationEnabled = $recruiterVisitNotificationEnabled;
    }

    /**
     * @brief Get persisted site-wide colors JSON (accent and future keys).
     *
     * @param void No input parameter.
     * @return string|null JSON string or null when no custom colors are stored.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function getSiteColorsJson(): ?string
    {
        return $this->siteColorsJson;
    }

    /**
     * @brief Set persisted site-wide colors JSON.
     *
     * @param string|null $siteColorsJson JSON string or null to clear custom colors.
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function setSiteColorsJson(?string $siteColorsJson): void
    {
        $this->siteColorsJson = $siteColorsJson;
    }

    /**
     * @brief Get persisted site-wide mail templates JSON.
     *
     * @param void No input parameter.
     * @return string|null Stored JSON or null when defaults apply.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getMailTemplatesJson(): ?string
    {
        return $this->mailTemplatesJson;
    }

    /**
     * @brief Set persisted site-wide mail templates JSON.
     *
     * @param string|null $mailTemplatesJson JSON string or null to clear custom templates.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function setMailTemplatesJson(?string $mailTemplatesJson): void
    {
        $this->mailTemplatesJson = $mailTemplatesJson;
    }

    public function getIntroTitleCssSanitized(): ?string
    {
        return $this->introTitleCssSanitized;
    }

    public function setIntroTitleCssSanitized(?string $introTitleCssSanitized): void
    {
        $this->introTitleCssSanitized = $introTitleCssSanitized;
    }

    public function getWebcvButtonCssSanitized(): ?string
    {
        return $this->webcvButtonCssSanitized;
    }

    public function setWebcvButtonCssSanitized(?string $webcvButtonCssSanitized): void
    {
        $this->webcvButtonCssSanitized = $webcvButtonCssSanitized;
    }

    public function getWebcvButtonCssHoverSanitized(): ?string
    {
        return $this->webcvButtonCssHoverSanitized;
    }

    public function setWebcvButtonCssHoverSanitized(?string $webcvButtonCssHoverSanitized): void
    {
        $this->webcvButtonCssHoverSanitized = $webcvButtonCssHoverSanitized;
    }

    /**
     * @brief Return translation rows.
     * @param void No input parameter.
     * @return Collection<int, HomeCustomizationTranslation>
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(HomeCustomizationTranslation $translation): void
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setCustomization($this);
        }
    }

    public function removeTranslation(HomeCustomizationTranslation $translation): void
    {
        $this->translations->removeElement($translation);
    }

    public function getIntroTextForLocale(string $locale): string
    {
        foreach ($this->translations as $translation) {
            if (strtolower($translation->getLocale()) === strtolower($locale)) {
                return $translation->getIntroText();
            }
        }

        return '';
    }

    public function getMetaDescriptionForLocale(string $locale): string
    {
        foreach ($this->translations as $translation) {
            if (strtolower($translation->getLocale()) === strtolower($locale)) {
                return $translation->getMetaDescription();
            }
        }

        return '';
    }

    public function setMetaDescriptionForLocale(string $locale, string $metaDescription): void
    {
        foreach ($this->translations as $translation) {
            if (strtolower($translation->getLocale()) === strtolower($locale)) {
                $translation->setMetaDescription($metaDescription);

                return;
            }
        }

        $translation = new HomeCustomizationTranslation();
        $translation->setLocale($locale);
        $translation->setIntroText('');
        $translation->setMetaDescription($metaDescription);
        $this->addTranslation($translation);
    }

    /**
     * @brief Return custom quick tile rows.
     *
     * @param void No input parameter.
     * @return Collection<int, HomeQuickTile>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function getQuickTiles(): Collection
    {
        return $this->quickTiles;
    }

    /**
     * @brief Attach a custom quick tile.
     * @param HomeQuickTile $tile Tile entity.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function addQuickTile(HomeQuickTile $tile): void
    {
        if (!$this->quickTiles->contains($tile)) {
            $this->quickTiles->add($tile);
            $tile->setCustomization($this);
        }
    }

    /**
     * @brief Detach a custom quick tile.
     * @param HomeQuickTile $tile Tile entity.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function removeQuickTile(HomeQuickTile $tile): void
    {
        $this->quickTiles->removeElement($tile);
    }
}
