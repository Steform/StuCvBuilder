<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvEducationOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvEducationAdminUpdateService;
use App\Service\Cv\CvEducationSettingsService;
use App\Service\Cv\EducationContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Education section customization (admin UI + public CV merge).
 */
class CompanyCvEducationCustomizationService
{
    public const CSRF_EDUCATION_SAVE = 'employment_company_cv_education';

    public const CSRF_EDUCATION_ENABLE = 'employment_company_cv_education_enable';

    public const CSRF_EDUCATION_RESET = 'employment_company_cv_education_reset';

    /**
     * @brief Wire company Education customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvEducationAdminUpdateService $cvEducationAdminUpdateService Education POST applier.
     * @param CvEducationSettingsService $cvEducationSettingsService Education projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvEducationAdminUpdateService $cvEducationAdminUpdateService,
        private readonly CvEducationSettingsService $cvEducationSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Education override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isEducationCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EDUCATION) !== null;
    }

    /**
     * @brief Merge company Education override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function mergeEducationOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EDUCATION);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvEducationOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvEducationOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global Education settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function enableEducationCustomization(TrackedCompany $company): void
    {
        if ($this->isEducationCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvEducationOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                EducationContract::KEY_ENTRIES_BY_LOCALE => $this->buildInitialEntriesMapFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::EDUCATION,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Education override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resetEducationToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EDUCATION);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Education admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveEducationFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EDUCATION);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.education.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        $payload = CompanyCvEducationOverrideScope::decodeJson($override->getContentJson());
        $result = $this->cvEducationAdminUpdateService->applyEducationFromRequest($payload, $request, $activeLocales);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $sanitized = CompanyCvEducationOverrideScope::sanitizeForPersistence($result['payload']);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.education.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Education admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-06-01
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

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvEducationSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EDUCATION);
        $isCustomized = $override !== null;

        $educationPayload = $isCustomized
            ? CompanyCvEducationOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvEducationOverrideScope::extractFromProfilePayload($globalPayload);

        if ($educationPayload === [] && !$isCustomized) {
            $educationPayload = [
                EducationContract::KEY_ENTRIES_BY_LOCALE => $globalResolved['entriesByLocale'],
            ];
        }

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

        $panelParam = $request->query->get('panel');
        $educationPanel = is_string($panelParam) && $panelParam === 'education_entries'
            ? 'education_entries'
            : 'education_entries';

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
            'cvCustomizationActivePanel' => $educationPanel,
            'cvEducationFormAction' => null,
            'cvEducationFormScope' => 'company_cv_education_save',
            'cvEducationCsrfTokenId' => self::CSRF_EDUCATION_SAVE,
            'cvEducationCustomizationSection' => CompanyCvCustomizationSectionKey::EDUCATION,
        ];
    }

    /**
     * @brief Load latest global CV profile decoded payload.
     *
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
     * @brief Build initial entries map when global profile has no persisted Education key yet.
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
        $resolved = $this->cvEducationSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $defaultLocale,
        );

        $map = [];
        foreach ($resolved['entriesByLocale'] as $locale => $rows) {
            if (!is_string($locale) || !is_array($rows)) {
                continue;
            }

            $map[$locale] = $rows;
        }

        return $map;
    }
}
