<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvLanguagesOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvLanguagesAdminUpdateService;
use App\Service\Cv\CvLanguagesSettingsService;
use App\Service\Cv\LanguagesContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Languages section customization (admin UI + public CV merge).
 */
class CompanyCvLanguagesCustomizationService
{
    public const CSRF_LANGUAGES_SAVE = 'employment_company_cv_languages';

    public const CSRF_LANGUAGES_ENABLE = 'employment_company_cv_languages_enable';

    public const CSRF_LANGUAGES_RESET = 'employment_company_cv_languages_reset';

    /**
     * @brief Wire company Languages customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvLanguagesAdminUpdateService $cvLanguagesAdminUpdateService Languages POST applier.
     * @param CvLanguagesSettingsService $cvLanguagesSettingsService Languages projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvLanguagesAdminUpdateService $cvLanguagesAdminUpdateService,
        private readonly CvLanguagesSettingsService $cvLanguagesSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Languages override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function isLanguagesCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::LANGUAGES) !== null;
    }

    /**
     * @brief Merge company Languages override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function mergeLanguagesOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::LANGUAGES);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvLanguagesOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvLanguagesOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global Languages settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function enableLanguagesCustomization(TrackedCompany $company): void
    {
        if ($this->isLanguagesCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvLanguagesOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                LanguagesContract::KEY_ENTRIES => $this->buildInitialEntriesFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::LANGUAGES,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Languages override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resetLanguagesToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::LANGUAGES);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Languages admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function saveLanguagesFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::LANGUAGES);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.languages.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $payload = CompanyCvLanguagesOverrideScope::decodeJson($override->getContentJson());
        $result = $this->cvLanguagesAdminUpdateService->applyLanguagesFromRequest(
            $payload,
            $request,
            $activeLocales,
            $defaultLocale,
        );

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $sanitized = CompanyCvLanguagesOverrideScope::sanitizeForPersistence($result['payload']);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.languages.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Languages admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for panel state.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function buildLanguagesAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $displayLocale = (string) $request->getLocale();

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvLanguagesSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale,
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::LANGUAGES);
        $isCustomized = $override !== null;

        $languagesPayload = $isCustomized
            ? CompanyCvLanguagesOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvLanguagesOverrideScope::extractFromProfilePayload($globalPayload);

        if ($languagesPayload === [] && !$isCustomized) {
            $languagesPayload = [
                LanguagesContract::KEY_ENTRIES => $globalResolved['entries'],
            ];
        }

        $overrideContentJson = json_encode($languagesPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvLanguagesSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale,
        );

        $panelParam = $request->query->get('panel');
        $languagesPanel = is_string($panelParam) && $panelParam === 'languages_entries'
            ? 'languages_entries'
            : 'languages_entries';

        $languagesTexture = SituationBackgroundTexture::fromStored(
            SectionBackgroundContract::resolveTextureForSection($globalPayload, 'languages')
        );

        return [
            'cvLanguagesCustomizationEnabled' => $isCustomized,
            'cvLanguagesInheritedEntries' => $globalResolved['entries'],
            'cvLanguagesEntries' => $overrideResolved['canonicalEntries'],
            'cvLanguagesPreviewEntries' => $overrideResolved['entries'],
            'cvLanguagesBackgroundTexture' => $languagesTexture->value,
            'cvLanguagesHideSectionCustomization' => true,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActivePanel' => $languagesPanel,
            'cvLanguagesFormAction' => null,
            'cvLanguagesFormScope' => 'company_cv_languages_save',
            'cvLanguagesCsrfTokenId' => self::CSRF_LANGUAGES_SAVE,
            'cvLanguagesCustomizationSection' => CompanyCvCustomizationSectionKey::LANGUAGES,
        ];
    }

    /**
     * @brief Load latest global CV profile decoded payload.
     *
     * @return array<string, mixed>
     * @date 2026-06-09
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
     * @brief Build initial entries when global profile has no persisted Languages key yet.
     *
     * @param array<string, mixed> $globalPayload Global profile payload.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function buildInitialEntriesFromGlobal(array $globalPayload): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $contentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';

        $resolved = $this->cvLanguagesSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $defaultLocale,
        );

        return $resolved['canonicalEntries'];
    }
}
