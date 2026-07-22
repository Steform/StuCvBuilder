<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Cv\CvInvalidFormatPolicy;
use App\Cv\CvPublicAccessMode;
use App\Cv\SiteColorsContract;
use App\Service\Home\HomeCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Backoffice site-wide configuration (favicon, CV antibot threshold).
 */
class SiteConfigurationService
{
    /**
     * @brief Build site configuration service.
     *
     * @param HomeCustomizationService $homeCustomizationService Home customization persistence.
     * @param EntityManagerInterface $entityManager Doctrine entity manager.
     * @param int $defaultCvAntibotThreshold Fallback when singleton row is missing.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function __construct(
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly SiteColorsResolver $siteColorsResolver,
        private readonly SiteMailTemplateAdminService $siteMailTemplateAdminService,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $defaultCvAntibotThreshold = 50,
    ) {
    }

    /**
     * @brief Resolve CV antibot threshold from persisted site configuration.
     *
     * @return int Threshold between 0 and 100.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function getCvAntibotThreshold(): int
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();

        return $customization->getCvAntibotThreshold();
    }

    /**
     * @brief Resolve accent color for site identity admin form display.
     *
     * @return string Resolved `#rrggbb` accent color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function getResolvedAccentColor(): string
    {
        return $this->siteColorsResolver->resolveAccentColor();
    }

    /**
     * @brief Resolve CV menu background for site identity admin form display.
     *
     * @return string Resolved `#rrggbb` menu background color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function getResolvedCvMenuBackground(): string
    {
        return $this->siteColorsResolver->resolveCvMenuBackground();
    }

    /**
     * @brief Resolve whether public home and CV routes are in maintenance mode.
     *
     * @return bool True when maintenance mode is active.
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function isMaintenanceModeEnabled(): bool
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();

        return $customization->isMaintenanceModeEnabled();
    }

    /**
     * @brief Resolve whether recruiter visit email notifications are enabled.
     *
     * @return bool True when recruiter visit notifications are active.
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function isRecruiterVisitNotificationEnabled(): bool
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();

        return $customization->isRecruiterVisitNotificationEnabled();
    }

    /**
     * @brief Resolve configured public CV access mode.
     *
     * @return CvPublicAccessMode Active access mode.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getCvPublicAccessMode(): CvPublicAccessMode
    {
        return $this->homeCustomizationService->getOrCreateSingleton()->getCvPublicAccessMode();
    }

    /**
     * @brief Resolve configured invalid recruiter format policy.
     *
     * @return CvInvalidFormatPolicy Active invalid-format policy.
     * @date 2026-07-22
     * @author Stephane H.
     */
    public function getCvInvalidFormatPolicy(): CvInvalidFormatPolicy
    {
        return $this->homeCustomizationService->getOrCreateSingleton()->getCvInvalidFormatPolicy();
    }

    /**
     * @brief Persist site favicon and CV antibot threshold from admin POST.
     *
     * @param Request $request Admin configuration form request.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function saveFromAdminRequest(Request $request): void
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();
        $activeLocales = $this->resolveActiveLocales();

        $resetType = trim((string) $request->request->get('mail_templates_reset_type', ''));
        if ($resetType !== '') {
            $this->siteMailTemplateAdminService->resetTypeToDefaults($resetType, $activeLocales);
        } else {
            $this->siteMailTemplateAdminService->applyFromAdminRequest($request, $activeLocales);
        }

        $this->homeCustomizationService->applySiteFaviconFromAdminRequest($request, $customization);
        $this->homeCustomizationService->applyOpenGraphImageFromAdminRequest($request, $customization);
        $this->homeCustomizationService->applySiteSeoMetaDescriptionsFromAdminRequest($request, $activeLocales);

        $threshold = (int) $request->request->get('cv_antibot_threshold', (string) $this->defaultCvAntibotThreshold);
        $customization->setCvAntibotThreshold($threshold);
        $customization->setMaintenanceModeEnabled($request->request->getBoolean('maintenance_mode_enabled'));
        $customization->setRecruiterVisitNotificationEnabled($request->request->getBoolean('recruiter_visit_notification_enabled'));
        $customization->setCvPublicAccessMode($this->resolveSubmittedPublicAccessMode($request));
        $customization->setCvInvalidFormatPolicy($this->resolveSubmittedInvalidFormatPolicy($request));

        /** @var array<string, mixed> $siteColorsSubmitted */
        $siteColorsSubmitted = $request->request->all('site_colors');
        $existingSiteColors = SiteColorsContract::decodeFromStorage($customization->getSiteColorsJson());
        $mergedSiteColors = SiteColorsContract::mergeSubmitted($existingSiteColors, $siteColorsSubmitted);
        $customization->setSiteColorsJson(SiteColorsContract::encodeForStorage($mergedSiteColors));

        $this->entityManager->persist($customization);
        $this->entityManager->flush();
    }

    /**
     * @brief Resolve active locale codes for site configuration forms.
     *
     * @return list<string>
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function getActiveLocales(): array
    {
        return $this->resolveActiveLocales();
    }

    /**
     * @brief Resolve active locale codes with supported fallback.
     *
     * @return list<string>
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function resolveActiveLocales(): array
    {
        $configuration = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($configuration['activeLocales'] ?? null) ? $configuration['activeLocales'] : [];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }

        return $activeLocales;
    }

    /**
     * @brief Resolve submitted public CV access mode with safe fallback.
     *
     * @param Request $request Admin configuration form request.
     * @return CvPublicAccessMode Validated access mode.
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function resolveSubmittedPublicAccessMode(Request $request): CvPublicAccessMode
    {
        $submitted = trim((string) $request->request->get('cv_public_access_mode', CvPublicAccessMode::Gated->value));
        $resolved = CvPublicAccessMode::tryFrom($submitted);

        return $resolved ?? CvPublicAccessMode::Gated;
    }

    /**
     * @brief Resolve submitted invalid recruiter format policy with safe fallback.
     *
     * @param Request $request Admin configuration form request.
     * @return CvInvalidFormatPolicy Validated invalid-format policy.
     * @date 2026-07-22
     * @author Stephane H.
     */
    private function resolveSubmittedInvalidFormatPolicy(Request $request): CvInvalidFormatPolicy
    {
        $submitted = trim((string) $request->request->get('cv_invalid_format_policy', CvInvalidFormatPolicy::Allow->value));
        $resolved = CvInvalidFormatPolicy::tryFrom($submitted);

        return $resolved ?? CvInvalidFormatPolicy::Allow;
    }
}
