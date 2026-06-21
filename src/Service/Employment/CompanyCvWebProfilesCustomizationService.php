<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvWebProfilesOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvWebProfilesAdminUpdateService;
use App\Service\Cv\CvWebProfilesSettingsService;
use App\Service\Cv\WebProfilesContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Web profiles section customization (admin UI + public CV merge).
 */
class CompanyCvWebProfilesCustomizationService
{
    public const CSRF_WEB_PROFILES_SAVE = 'employment_company_cv_web_profiles';

    public const CSRF_WEB_PROFILES_ENABLE = 'employment_company_cv_web_profiles_enable';

    public const CSRF_WEB_PROFILES_RESET = 'employment_company_cv_web_profiles_reset';

    /**
     * @brief Wire company Web profiles customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvWebProfilesAdminUpdateService $cvWebProfilesAdminUpdateService Web profiles POST applier.
     * @param CvWebProfilesSettingsService $cvWebProfilesSettingsService Web profiles projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvWebProfilesAdminUpdateService $cvWebProfilesAdminUpdateService,
        private readonly CvWebProfilesSettingsService $cvWebProfilesSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Web profiles override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function isWebProfilesCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::WEB_PROFILES) !== null;
    }

    /**
     * @brief Merge company Web profiles override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function mergeWebProfilesOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::WEB_PROFILES);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvWebProfilesOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvWebProfilesOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global Web profiles settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function enableWebProfilesCustomization(TrackedCompany $company): void
    {
        if ($this->isWebProfilesCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvWebProfilesOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                WebProfilesContract::KEY_ENTRIES => $this->buildInitialEntriesFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::WEB_PROFILES,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Web profiles override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resetWebProfilesToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::WEB_PROFILES);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Web profiles admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function saveWebProfilesFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::WEB_PROFILES);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.web_profiles.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $payload = CompanyCvWebProfilesOverrideScope::decodeJson($override->getContentJson());
        $result = $this->cvWebProfilesAdminUpdateService->applyWebProfilesFromRequest($payload, $request);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $sanitized = CompanyCvWebProfilesOverrideScope::sanitizeForPersistence($result['payload']);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.web_profiles.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Web profiles admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for panel state.
     * @return array<string, mixed>
     * @date 2026-06-09
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

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvWebProfilesSettingsService->resolveFromContentJson($globalContentJson, $displayLocale);

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::WEB_PROFILES);
        $isCustomized = $override !== null;

        $webProfilesPayload = $isCustomized
            ? CompanyCvWebProfilesOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvWebProfilesOverrideScope::extractFromProfilePayload($globalPayload);

        if ($webProfilesPayload === [] && !$isCustomized) {
            $webProfilesPayload = [
                WebProfilesContract::KEY_ENTRIES => $globalResolved['entries'],
            ];
        }

        $overrideContentJson = json_encode($webProfilesPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvWebProfilesSettingsService->resolveFromContentJson($overrideContentJson, $displayLocale);

        $panelParam = $request->query->get('panel');
        $webProfilesPanel = is_string($panelParam) && $panelParam === 'web_profiles_entries'
            ? 'web_profiles_entries'
            : 'web_profiles_entries';

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
            'cvCustomizationActivePanel' => $webProfilesPanel,
            'cvWebProfilesFormAction' => null,
            'cvWebProfilesFormScope' => 'company_cv_web_profiles_save',
            'cvWebProfilesCsrfTokenId' => self::CSRF_WEB_PROFILES_SAVE,
            'cvWebProfilesCustomizationSection' => CompanyCvCustomizationSectionKey::WEB_PROFILES,
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
     * @brief Build initial entries when global profile has no persisted Web profiles key yet.
     *
     * @param array<string, mixed> $globalPayload Global profile payload.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function buildInitialEntriesFromGlobal(array $globalPayload): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : 'fr';
        $contentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $resolved = $this->cvWebProfilesSettingsService->resolveFromContentJson($contentJson, $defaultLocale);

        return $resolved['entries'];
    }
}
