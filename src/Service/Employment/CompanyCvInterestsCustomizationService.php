<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvInterestsOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvInterestsAdminUpdateService;
use App\Service\Cv\CvInterestsSettingsService;
use App\Service\Cv\InterestsContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Interests section customization (admin UI + public CV merge).
 */
class CompanyCvInterestsCustomizationService
{
    public const CSRF_INTERESTS_SAVE = 'employment_company_cv_interests';

    public const CSRF_INTERESTS_ENABLE = 'employment_company_cv_interests_enable';

    public const CSRF_INTERESTS_RESET = 'employment_company_cv_interests_reset';

    /**
     * @brief Wire company Interests customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvInterestsAdminUpdateService $cvInterestsAdminUpdateService Interests POST applier.
     * @param CvInterestsSettingsService $cvInterestsSettingsService Interests projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvInterestsAdminUpdateService $cvInterestsAdminUpdateService,
        private readonly CvInterestsSettingsService $cvInterestsSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Interests override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function isInterestsCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::INTERESTS) !== null;
    }

    /**
     * @brief Merge company Interests override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function mergeInterestsOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::INTERESTS);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvInterestsOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvInterestsOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global Interests settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function enableInterestsCustomization(TrackedCompany $company): void
    {
        if ($this->isInterestsCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvInterestsOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                InterestsContract::KEY_ENTRIES => $this->buildInitialEntriesFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::INTERESTS,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Interests override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resetInterestsToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::INTERESTS);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Interests admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function saveInterestsFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::INTERESTS);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.interests.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $payload = CompanyCvInterestsOverrideScope::decodeJson($override->getContentJson());
        $result = $this->cvInterestsAdminUpdateService->applyInterestsFromRequest(
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

        $sanitized = CompanyCvInterestsOverrideScope::sanitizeForPersistence($result['payload'], $activeLocales, $defaultLocale);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.interests.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Interests admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function buildInterestsAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvInterestsSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::INTERESTS);
        $isCustomized = $override !== null;

        $interestsPayload = $isCustomized
            ? CompanyCvInterestsOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvInterestsOverrideScope::extractFromProfilePayload($globalPayload);

        if ($interestsPayload === [] && !$isCustomized) {
            $interestsPayload = [
                InterestsContract::KEY_ENTRIES => $globalResolved['canonicalEntries'],
            ];
        }

        $overrideContentJson = json_encode($interestsPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvInterestsSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $localeParam = $request->query->get('locale');
        $interestsLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        $panelParam = $request->query->get('panel');
        $interestsPanel = is_string($panelParam) && $panelParam === 'interests_entries'
            ? 'interests_entries'
            : 'interests_entries';

        $interestsTexture = SituationBackgroundTexture::fromStored(
            SectionBackgroundContract::resolveTextureForSection($globalPayload, 'interests')
        );

        return [
            'cvInterestsCustomizationEnabled' => $isCustomized,
            'cvInterestsInheritedEntries' => $globalResolved['canonicalEntries'],
            'cvInterestsEntries' => $overrideResolved['canonicalEntries'],
            'cvInterestsPreviewEntries' => $overrideResolved['entries'],
            'cvInterestsColumnsPerRow' => $overrideResolved['columnsPerRow'],
            'cvInterestsColumnsPerRowSmall' => $overrideResolved['columnsPerRowSmall'],
            'cvInterestsBackgroundTexture' => $interestsTexture->value,
            'cvInterestsHideSectionCustomization' => true,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $interestsLocale,
            'cvCustomizationActivePanel' => $interestsPanel,
            'cvInterestsFormAction' => null,
            'cvInterestsFormScope' => 'company_cv_interests_save',
            'cvInterestsCsrfTokenId' => self::CSRF_INTERESTS_SAVE,
            'cvInterestsCustomizationSection' => CompanyCvCustomizationSectionKey::INTERESTS,
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
     * @brief Build initial entries list when global profile has no persisted Interests key yet.
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
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $contentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $resolved = $this->cvInterestsSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $defaultLocale,
        );

        return $resolved['canonicalEntries'];
    }
}
