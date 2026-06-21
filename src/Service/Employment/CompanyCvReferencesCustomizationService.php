<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvReferencesOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvReferencesAdminUpdateService;
use App\Service\Cv\CvReferencesSettingsService;
use App\Service\Cv\ReferencesContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company References section customization (admin UI + public CV merge).
 */
class CompanyCvReferencesCustomizationService
{
    public const CSRF_REFERENCES_SAVE = 'employment_company_cv_references';

    public const CSRF_REFERENCES_ENABLE = 'employment_company_cv_references_enable';

    public const CSRF_REFERENCES_RESET = 'employment_company_cv_references_reset';

    /**
     * @brief Wire company References customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvReferencesAdminUpdateService $cvReferencesAdminUpdateService References POST applier.
     * @param CvReferencesSettingsService $cvReferencesSettingsService References projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvReferencesAdminUpdateService $cvReferencesAdminUpdateService,
        private readonly CvReferencesSettingsService $cvReferencesSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted References override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function isReferencesCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::REFERENCES) !== null;
    }

    /**
     * @brief Merge company References override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function mergeReferencesOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::REFERENCES);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvReferencesOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvReferencesOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global References settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function enableReferencesCustomization(TrackedCompany $company): void
    {
        if ($this->isReferencesCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvReferencesOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                ReferencesContract::KEY_SECTION_ENABLED => ReferencesContract::isSectionEnabledFromPayload($globalPayload),
                ReferencesContract::KEY_ENTRIES_BY_LOCALE => $this->buildInitialEntriesMapFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::REFERENCES,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company References override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resetReferencesToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::REFERENCES);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply References admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function saveReferencesFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::REFERENCES);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.references.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        $payload = CompanyCvReferencesOverrideScope::decodeJson($override->getContentJson());
        $result = $this->cvReferencesAdminUpdateService->applyReferencesFromRequest($payload, $request, $activeLocales);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $sanitized = CompanyCvReferencesOverrideScope::sanitizeForPersistence($result['payload']);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.references.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company References admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function buildReferencesAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvReferencesSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::REFERENCES);
        $isCustomized = $override !== null;

        $referencesPayload = $isCustomized
            ? CompanyCvReferencesOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvReferencesOverrideScope::extractFromProfilePayload($globalPayload);

        if ($referencesPayload === [] && !$isCustomized) {
            $referencesPayload = [
                ReferencesContract::KEY_SECTION_ENABLED => $globalResolved['sectionEnabled'],
                ReferencesContract::KEY_ENTRIES_BY_LOCALE => $globalResolved['entriesByLocale'],
            ];
        }

        $overrideContentJson = json_encode($referencesPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvReferencesSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $localeParam = $request->query->get('locale');
        $referencesLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        $panelParam = $request->query->get('panel');
        $referencesPanel = is_string($panelParam) && in_array($panelParam, ['section', 'references_entries'], true)
            ? $panelParam
            : 'references_entries';

        $referencesTexture = SituationBackgroundTexture::fromStored(
            SectionBackgroundContract::resolveTextureForSection($globalPayload, 'references')
        );

        return [
            'cvReferencesCustomizationEnabled' => $isCustomized,
            'cvReferencesInheritedEntries' => $globalResolved['entriesByLocale'][$defaultLocale] ?? [],
            'cvReferencesSectionEnabled' => $overrideResolved['sectionEnabled'],
            'cvReferencesEntriesByLocale' => $overrideResolved['entriesByLocale'],
            'cvReferencesPreviewByLocale' => $this->cvReferencesSettingsService->buildAdminPreviewPayloadByLocale(
                $overrideResolved['entriesByLocale'],
                $overrideResolved['sectionEnabled'],
            ),
            'cvReferencesBackgroundTexture' => $referencesTexture->value,
            'cvReferencesHideSectionCustomization' => false,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $referencesLocale,
            'cvCustomizationActivePanel' => $referencesPanel,
            'cvReferencesFormAction' => null,
            'cvReferencesFormScope' => 'company_cv_references_save',
            'cvReferencesCsrfTokenId' => self::CSRF_REFERENCES_SAVE,
            'cvReferencesCustomizationSection' => CompanyCvCustomizationSectionKey::REFERENCES,
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
     * @brief Build initial entries map when global profile has no persisted References key yet.
     *
     * @param array<string, mixed> $globalPayload Global profile payload.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function buildInitialEntriesMapFromGlobal(array $globalPayload): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $contentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $resolved = $this->cvReferencesSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $defaultLocale,
        );

        return $resolved['entriesByLocale'];
    }
}
