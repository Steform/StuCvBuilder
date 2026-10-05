<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvExperienceAdminUpdateService;
use App\Service\Cv\CvExperienceSettingsService;
use App\Service\Cv\ExperienceContract;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Experience section customization (admin UI + public CV merge).
 */
class CompanyCvExperienceCustomizationService
{
    public const CSRF_EXPERIENCE_SAVE = 'employment_company_cv_experience';

    /**
     * @brief Wire company Experience customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvExperienceAdminUpdateService $cvExperienceAdminUpdateService Experience POST applier.
     * @param CvExperienceSettingsService $cvExperienceSettingsService Experience projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvExperienceAdminUpdateService $cvExperienceAdminUpdateService,
        private readonly CvExperienceSettingsService $cvExperienceSettingsService,
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
    public function isExperienceCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply Experience admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveExperienceFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.experience.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        $payload = $this->companyCvProfilePayloadService->loadPayload($company);
        $result = $this->cvExperienceAdminUpdateService->applyExperienceFromRequest($payload, $request, $activeLocales);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $this->companyCvProfilePayloadService->savePayload($company, $result['payload']);
        $flashSuccess[] = 'employment.companies.cv_customization.experience.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Experience admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function buildExperienceAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadGlobalPayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvExperienceSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $isCustomized = $company->isCvContentCustom();
        $experiencePayload = $isCustomized
            ? $this->companyCvProfilePayloadService->loadPayload($company)
            : $globalPayload;

        $overrideContentJson = json_encode($experiencePayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvExperienceSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $localeParam = $request->query->get('locale');
        $experienceLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        $entryParam = $request->query->get('entry');
        $experienceEntry = is_string($entryParam) && ExperienceContract::isValidUuid(trim($entryParam))
            ? trim($entryParam)
            : null;

        $experienceTexture = SituationBackgroundTexture::fromStored(
            SectionBackgroundContract::resolveTextureForSection($globalPayload, 'experience')
        );

        return [
            'cvExperienceCustomizationEnabled' => $isCustomized,
            'cvExperienceInheritedEntries' => $globalResolved['entriesByLocale'][$defaultLocale] ?? [],
            'cvExperienceEntriesByLocale' => $overrideResolved['entriesByLocale'],
            'cvExperienceCategories' => $overrideResolved['categories'],
            'cvExperiencePreviewByLocale' => $this->cvExperienceSettingsService->buildAdminPreviewPayloadByLocale(
                $overrideResolved['entriesByLocale']
            ),
            'cvExperienceBackgroundTexture' => $experienceTexture->value,
            'cvExperienceHideSectionCustomization' => true,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $experienceLocale,
            'cvCustomizationActivePanel' => 'professional_entries',
            'cvCustomizationActiveEntry' => $experienceEntry,
            'cvExperienceFormAction' => null,
            'cvExperienceFormScope' => 'company_cv_experience_save',
            'cvExperienceCsrfTokenId' => self::CSRF_EXPERIENCE_SAVE,
            'cvExperienceCustomizationSection' => CompanyCvCustomizationSectionKey::EXPERIENCE,
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
