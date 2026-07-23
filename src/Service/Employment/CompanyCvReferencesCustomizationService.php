<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvReferencesAdminUpdateService;
use App\Service\Cv\CvReferencesSettingsService;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company References section customization (admin UI + public CV merge).
 */
class CompanyCvReferencesCustomizationService
{
    public const CSRF_REFERENCES_SAVE = 'employment_company_cv_references';

    /**
     * @brief Wire company References customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvReferencesAdminUpdateService $cvReferencesAdminUpdateService References POST applier.
     * @param CvReferencesSettingsService $cvReferencesSettingsService References projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvReferencesAdminUpdateService $cvReferencesAdminUpdateService,
        private readonly CvReferencesSettingsService $cvReferencesSettingsService,
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
    public function isReferencesCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply References admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveReferencesFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.references.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        $payload = $this->companyCvProfilePayloadService->loadPayload($company);
        $result = $this->cvReferencesAdminUpdateService->applyReferencesFromRequest($payload, $request, $activeLocales);

        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== [] || $flashWarning !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $this->companyCvProfilePayloadService->savePayload($company, $result['payload']);
        $flashSuccess[] = 'employment.companies.cv_customization.references.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company References admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-07-23
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

        $globalPayload = $this->loadGlobalPayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvReferencesSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $isCustomized = $company->isCvContentCustom();
        $referencesPayload = $isCustomized
            ? $this->companyCvProfilePayloadService->loadPayload($company)
            : $globalPayload;

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
