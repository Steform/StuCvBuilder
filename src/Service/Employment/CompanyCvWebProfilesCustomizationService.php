<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvWebProfilesAdminUpdateService;
use App\Service\Cv\CvWebProfilesSettingsService;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Web profiles section customization (admin UI + public CV merge).
 */
class CompanyCvWebProfilesCustomizationService
{
    public const CSRF_WEB_PROFILES_SAVE = 'employment_company_cv_web_profiles';

    /**
     * @brief Wire company Web profiles customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvWebProfilesAdminUpdateService $cvWebProfilesAdminUpdateService Web profiles POST applier.
     * @param CvWebProfilesSettingsService $cvWebProfilesSettingsService Web profiles projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvWebProfilesAdminUpdateService $cvWebProfilesAdminUpdateService,
        private readonly CvWebProfilesSettingsService $cvWebProfilesSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company uses an independent CV clone (custom mode).
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function isWebProfilesCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply Web profiles admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveWebProfilesFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.web_profiles.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $payload = $this->companyCvProfilePayloadService->loadPayload($company);
        $result = $this->cvWebProfilesAdminUpdateService->applyWebProfilesFromRequest($payload, $request);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $this->companyCvProfilePayloadService->savePayload($company, $result['payload']);
        $flashSuccess[] = 'employment.companies.cv_customization.web_profiles.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Web profiles admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for panel state.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function buildWebProfilesAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $displayLocale = (string) $request->getLocale();

        $globalPayload = $this->loadGlobalPayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvWebProfilesSettingsService->resolveFromContentJson($globalContentJson, $displayLocale);

        $isCustomized = $company->isCvContentCustom();
        $webProfilesPayload = $isCustomized
            ? $this->companyCvProfilePayloadService->loadPayload($company)
            : $globalPayload;

        $overrideContentJson = json_encode($webProfilesPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvWebProfilesSettingsService->resolveFromContentJson($overrideContentJson, $displayLocale);

        $webProfilesTexture = SituationBackgroundTexture::fromStored(
            SectionBackgroundContract::resolveTextureForSection($globalPayload, 'web_profiles')
        );

        return [
            'cvWebProfilesCustomizationEnabled' => $isCustomized,
            'cvWebProfilesInheritedEntries' => $globalResolved['entries'],
            'cvWebProfilesEntries' => $overrideResolved['entries'],
            'cvWebProfilesBackgroundTexture' => $webProfilesTexture->value,
            'cvWebProfilesHideSectionCustomization' => true,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActivePanel' => 'web_profiles_entries',
            'cvWebProfilesFormAction' => null,
            'cvWebProfilesFormScope' => 'company_cv_web_profiles_save',
            'cvWebProfilesCsrfTokenId' => self::CSRF_WEB_PROFILES_SAVE,
            'cvWebProfilesCustomizationSection' => CompanyCvCustomizationSectionKey::WEB_PROFILES,
        ];
    }

    /**
     * @brief Load sanitized global CV profile payload (empty array when missing).
     *
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    private function loadGlobalPayload(): array
    {
        return CvProfilePersistenceScope::sanitizeForPersistence(
            $this->companyCvProfilePayloadService->loadGlobalPayload()
        );
    }
}
