<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvExperienceOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvExperienceAdminUpdateService;
use App\Service\Cv\CvExperienceSettingsService;
use App\Service\Cv\ExperienceContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Experience section customization (admin UI + public CV merge).
 */
class CompanyCvExperienceCustomizationService
{
    public const CSRF_EXPERIENCE_SAVE = 'employment_company_cv_experience';

    public const CSRF_EXPERIENCE_ENABLE = 'employment_company_cv_experience_enable';

    public const CSRF_EXPERIENCE_RESET = 'employment_company_cv_experience_reset';

    /**
     * @brief Wire company Experience customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvExperienceAdminUpdateService $cvExperienceAdminUpdateService Experience POST applier.
     * @param CvExperienceSettingsService $cvExperienceSettingsService Experience projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvExperienceAdminUpdateService $cvExperienceAdminUpdateService,
        private readonly CvExperienceSettingsService $cvExperienceSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Experience override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isExperienceCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EXPERIENCE) !== null;
    }

    /**
     * @brief Merge company Experience override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function mergeExperienceOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EXPERIENCE);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvExperienceOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvExperienceOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global Experience settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function enableExperienceCustomization(TrackedCompany $company): void
    {
        if ($this->isExperienceCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvExperienceOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                ExperienceContract::KEY_ENTRIES_BY_LOCALE => $this->buildInitialEntriesMapFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::EXPERIENCE,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Experience override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resetExperienceToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EXPERIENCE);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Experience admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveExperienceFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EXPERIENCE);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.experience.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        $payload = CompanyCvExperienceOverrideScope::decodeJson($override->getContentJson());
        $result = $this->cvExperienceAdminUpdateService->applyExperienceFromRequest($payload, $request, $activeLocales);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $sanitized = CompanyCvExperienceOverrideScope::sanitizeForPersistence($result['payload']);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.experience.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Experience admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-06-01
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

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvExperienceSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::EXPERIENCE);
        $isCustomized = $override !== null;

        $experiencePayload = $isCustomized
            ? CompanyCvExperienceOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvExperienceOverrideScope::extractFromProfilePayload($globalPayload);

        if ($experiencePayload === [] && !$isCustomized) {
            $experiencePayload = [
                ExperienceContract::KEY_ENTRIES_BY_LOCALE => $globalResolved['entriesByLocale'],
            ];
        }

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

        $panelParam = $request->query->get('panel');
        $experiencePanel = is_string($panelParam) && $panelParam === 'professional_entries'
            ? 'professional_entries'
            : 'professional_entries';

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
            'cvExperiencePreviewByLocale' => $this->cvExperienceSettingsService->buildAdminPreviewPayloadByLocale(
                $overrideResolved['entriesByLocale']
            ),
            'cvExperienceBackgroundTexture' => $experienceTexture->value,
            'cvExperienceHideSectionCustomization' => true,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $experienceLocale,
            'cvCustomizationActivePanel' => $experiencePanel,
            'cvCustomizationActiveEntry' => $experienceEntry,
            'cvExperienceFormAction' => null,
            'cvExperienceFormScope' => 'company_cv_experience_save',
            'cvExperienceCsrfTokenId' => self::CSRF_EXPERIENCE_SAVE,
            'cvExperienceCustomizationSection' => CompanyCvCustomizationSectionKey::EXPERIENCE,
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
     * @brief Build initial entries map when global profile has no persisted Experience key yet.
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
        $resolved = $this->cvExperienceSettingsService->resolveFromContentJson(
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

        return ExperienceContract::syncIsPrimaryAcrossLocales($map);
    }
}
