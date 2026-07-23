<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvEducationAdminUpdateService;
use App\Service\Cv\CvEducationSettingsService;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Education section customization (admin UI + public CV merge).
 */
class CompanyCvEducationCustomizationService
{
    public const CSRF_EDUCATION_SAVE = 'employment_company_cv_education';

    /**
     * @brief Wire company Education customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvEducationAdminUpdateService $cvEducationAdminUpdateService Education POST applier.
     * @param CvEducationSettingsService $cvEducationSettingsService Education projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvEducationAdminUpdateService $cvEducationAdminUpdateService,
        private readonly CvEducationSettingsService $cvEducationSettingsService,
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
    public function isEducationCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply Education admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveEducationFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.education.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        $payload = $this->companyCvProfilePayloadService->loadPayload($company);
        $result = $this->cvEducationAdminUpdateService->applyEducationFromRequest($payload, $request, $activeLocales);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $this->companyCvProfilePayloadService->savePayload($company, $result['payload']);
        $flashSuccess[] = 'employment.companies.cv_customization.education.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Education admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function buildEducationAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadGlobalPayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvEducationSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $isCustomized = $company->isCvContentCustom();
        $educationPayload = $isCustomized
            ? $this->companyCvProfilePayloadService->loadPayload($company)
            : $globalPayload;

        $overrideContentJson = json_encode($educationPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvEducationSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $localeParam = $request->query->get('locale');
        $educationLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        $educationTexture = SituationBackgroundTexture::fromStored(
            SectionBackgroundContract::resolveTextureForSection($globalPayload, 'education')
        );

        return [
            'cvEducationCustomizationEnabled' => $isCustomized,
            'cvEducationInheritedEntries' => $globalResolved['entriesByLocale'][$defaultLocale] ?? [],
            'cvEducationEntriesByLocale' => $overrideResolved['entriesByLocale'],
            'cvEducationPreviewByLocale' => $this->cvEducationSettingsService->buildAdminPreviewPayloadByLocale(
                $overrideResolved['entriesByLocale']
            ),
            'cvEducationBackgroundTexture' => $educationTexture->value,
            'cvEducationHideSectionCustomization' => true,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $educationLocale,
            'cvCustomizationActivePanel' => 'education_entries',
            'cvEducationFormAction' => null,
            'cvEducationFormScope' => 'company_cv_education_save',
            'cvEducationCsrfTokenId' => self::CSRF_EDUCATION_SAVE,
            'cvEducationCustomizationSection' => CompanyCvCustomizationSectionKey::EDUCATION,
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
