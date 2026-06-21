<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvFlagshipProjectsOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvFlagshipProjectsAdminUpdateService;
use App\Service\Cv\CvFlagshipProjectsSettingsService;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Flagship projects section customization (admin UI + public CV merge).
 */
class CompanyCvFlagshipProjectsCustomizationService
{
    public const CSRF_FLAGSHIP_SAVE = 'employment_company_cv_flagship_projects';

    public const CSRF_FLAGSHIP_ENABLE = 'employment_company_cv_flagship_projects_enable';

    public const CSRF_FLAGSHIP_RESET = 'employment_company_cv_flagship_projects_reset';

    /**
     * @brief Wire company Flagship projects customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvFlagshipProjectsAdminUpdateService $cvFlagshipProjectsAdminUpdateService Flagship POST applier.
     * @param CvFlagshipProjectsSettingsService $cvFlagshipProjectsSettingsService Flagship projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvFlagshipProjectsAdminUpdateService $cvFlagshipProjectsAdminUpdateService,
        private readonly CvFlagshipProjectsSettingsService $cvFlagshipProjectsSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Flagship projects override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isFlagshipProjectsCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS) !== null;
    }

    /**
     * @brief Merge company Flagship projects override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function mergeFlagshipProjectsOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS);
        if ($override === null) {
            return $payload;
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null)
            ? $localeConfig['defaultLocale']
            : 'fr';

        $overridePayload = CompanyCvFlagshipProjectsOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvFlagshipProjectsOverrideScope::mergeIntoPayload($payload, $overridePayload, $defaultLocale);
    }

    /**
     * @brief Copy global Flagship projects settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function enableFlagshipProjectsCustomization(TrackedCompany $company): void
    {
        if ($this->isFlagshipProjectsCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvFlagshipProjectsOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                FlagshipProjectsContract::KEY_SECTION_ENABLED => FlagshipProjectsContract::isSectionEnabledFromPayload($globalPayload),
                FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => $this->buildInitialEntriesMapFromGlobal($globalPayload),
            ];
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : 'fr';
        $sanitized = CompanyCvFlagshipProjectsOverrideScope::sanitizeForPersistence($slice, $defaultLocale);

        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Flagship projects override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resetFlagshipProjectsToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Flagship projects admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{
     *     flashSuccess: list<string>,
     *     flashWarning: list<string>,
     *     flashError: list<string>,
     *     flashStructuredWarning: list<array{message: string, parameters: array<string, string>}>
     * }
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveFlagshipProjectsFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];
        $flashStructuredWarning = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.flagship_projects.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $payload = CompanyCvFlagshipProjectsOverrideScope::decodeJson($override->getContentJson());
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

        $sanitized = CompanyCvFlagshipProjectsOverrideScope::sanitizeForPersistence($result['payload'], $defaultLocale);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.flagship_projects.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError', 'flashStructuredWarning');
    }

    /**
     * @brief Build Twig variables for company Flagship projects admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-06-01
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

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvFlagshipProjectsSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::FLAGSHIP_PROJECTS);
        $isCustomized = $override !== null;

        $flagshipPayload = $isCustomized
            ? CompanyCvFlagshipProjectsOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvFlagshipProjectsOverrideScope::extractFromProfilePayload($globalPayload);

        if ($flagshipPayload === [] && !$isCustomized) {
            $flagshipPayload = [
                FlagshipProjectsContract::KEY_SECTION_ENABLED => FlagshipProjectsContract::isSectionEnabledFromPayload($globalPayload),
                FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => $globalResolved['entriesByLocale'],
            ];
        }

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
     * @brief Load latest global CV profile decoded payload.
     *
     * @param void No input parameter.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function loadLatestGlobalProfilePayload(): array
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            return [];
        }

        $decoded = json_decode($profile->getContentJson(), true);

        return is_array($decoded)
            ? CvProfilePersistenceScope::sanitizeForPersistence($decoded)
            : [];
    }

    /**
     * @brief Build initial entries map when global profile has no persisted Flagship key yet.
     *
     * @param array<string, mixed> $globalPayload Global profile payload.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildInitialEntriesMapFromGlobal(array $globalPayload): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $contentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $resolved = $this->cvFlagshipProjectsSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $defaultLocale,
        );

        return $resolved['entriesByLocale'];
    }
}
