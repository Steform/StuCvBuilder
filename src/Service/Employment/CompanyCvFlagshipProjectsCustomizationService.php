<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvFlagshipProjectsAdminUpdateService;
use App\Service\Cv\CvFlagshipProjectsSettingsService;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Flagship projects section customization (admin UI + public CV merge).
 */
class CompanyCvFlagshipProjectsCustomizationService
{
    public const CSRF_FLAGSHIP_SAVE = 'employment_company_cv_flagship_projects';

    /**
     * @brief Wire company Flagship projects customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvFlagshipProjectsAdminUpdateService $cvFlagshipProjectsAdminUpdateService Flagship POST applier.
     * @param CvFlagshipProjectsSettingsService $cvFlagshipProjectsSettingsService Flagship projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvFlagshipProjectsAdminUpdateService $cvFlagshipProjectsAdminUpdateService,
        private readonly CvFlagshipProjectsSettingsService $cvFlagshipProjectsSettingsService,
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
    public function isFlagshipProjectsCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply Flagship projects admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>,
     *     flashError: list<string>,
     *     flashStructuredWarning: list<array{message: string, parameters: array<string, string>}>
     * }
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveFlagshipProjectsFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];
        $flashStructuredWarning = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.flagship_projects.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $payload = $this->companyCvProfilePayloadService->loadPayload($company);
        $result = $this->cvFlagshipProjectsAdminUpdateService->applyFlagshipProjectsFromRequest(
            $payload,
            $request,
            $activeLocales,
            $defaultLocale,
        );

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashStructuredWarning = array_merge($flashStructuredWarning, $result['flashStructuredWarning']);

        if ($flashStructuredWarning !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        }

        $this->companyCvProfilePayloadService->savePayload($company, $result['payload']);
        $flashSuccess[] = 'employment.companies.cv_customization.flagship_projects.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
    }

    /**
     * @brief Build Twig variables for company Flagship projects admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function buildFlagshipProjectsAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadGlobalPayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvFlagshipProjectsSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $isCustomized = $company->isCvContentCustom();
        $flagshipPayload = $isCustomized
            ? $this->companyCvProfilePayloadService->loadPayload($company)
            : $globalPayload;

        $overrideContentJson = json_encode($flagshipPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvFlagshipProjectsSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $inheritedProjects = $globalResolved['canonicalProjects'];
        $sampleTitles = [];
        foreach (array_slice($inheritedProjects, 0, 4) as $project) {
            $title = is_array($project['locales'][$defaultLocale] ?? null)
                ? (string) ($project['locales'][$defaultLocale]['title'] ?? '')
                : '';
            if ($title !== '') {
                $sampleTitles[] = $title;
            }
        }

        return [
            'cvFlagshipProjectsCustomizationEnabled' => $isCustomized,
            'cvFlagshipProjectsInheritedSummary' => [
                'sectionEnabled' => FlagshipProjectsContract::isSectionEnabledFromPayload($globalPayload),
                'projectCount' => count($inheritedProjects),
                'sampleTitles' => $sampleTitles,
            ],
            'cvFlagshipProjectsSectionEnabled' => FlagshipProjectsContract::isSectionEnabledFromPayload($flagshipPayload),
            'cvFlagshipProjectsCanonical' => $overrideResolved['canonicalProjects'],
            'cvFlagshipProjectsMaxCount' => FlagshipProjectsContract::MAX_PROJECTS_PER_LOCALE,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvFlagshipProjectsFormAction' => null,
            'cvFlagshipProjectsFormScope' => 'company_cv_flagship_projects_save',
            'cvFlagshipProjectsCsrfTokenId' => self::CSRF_FLAGSHIP_SAVE,
            'cvFlagshipProjectsCustomizationSection' => CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS,
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
