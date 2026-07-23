<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvCertificationAdminUpdateService;
use App\Service\Cv\CvCertificationSettingsService;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Certification section customization (admin UI + public CV merge).
 */
class CompanyCvCertificationCustomizationService
{
    public const CSRF_CERTIFICATION_SAVE = 'employment_company_cv_certification';

    /**
     * @brief Wire company Certification customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvCertificationAdminUpdateService $cvCertificationAdminUpdateService Certification POST applier.
     * @param CvCertificationSettingsService $cvCertificationSettingsService Certification projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvCertificationAdminUpdateService $cvCertificationAdminUpdateService,
        private readonly CvCertificationSettingsService $cvCertificationSettingsService,
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
    public function isCertificationCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply Certification admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveCertificationFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.certification.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $payload = $this->companyCvProfilePayloadService->loadPayload($company);
        $result = $this->cvCertificationAdminUpdateService->applyCertificationFromRequest(
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

        $this->companyCvProfilePayloadService->savePayload($company, $result['payload']);
        $flashSuccess[] = 'employment.companies.cv_customization.certification.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Certification admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function buildCertificationAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadGlobalPayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvCertificationSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $isCustomized = $company->isCvContentCustom();
        $certificationPayload = $isCustomized
            ? $this->companyCvProfilePayloadService->loadPayload($company)
            : $globalPayload;

        $overrideContentJson = json_encode($certificationPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvCertificationSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $localeParam = $request->query->get('locale');
        $certificationLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        $certificationTexture = SituationBackgroundTexture::fromStored(
            SectionBackgroundContract::resolveTextureForSection($globalPayload, 'certification')
        );

        return [
            'cvCertificationCustomizationEnabled' => $isCustomized,
            'cvCertificationInheritedEntries' => $globalResolved['canonicalEntries'],
            'cvCertificationEntries' => $overrideResolved['canonicalEntries'],
            'cvCertificationPreviewByLocale' => $this->cvCertificationSettingsService->buildAdminPreviewPayloadByLocale(
                $overrideResolved['canonicalEntries'],
                $activeLocales,
                $defaultLocale,
            ),
            'cvCertificationBackgroundTexture' => $certificationTexture->value,
            'cvCertificationHideSectionCustomization' => true,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $certificationLocale,
            'cvCustomizationActivePanel' => 'certification_entries',
            'cvCertificationFormAction' => null,
            'cvCertificationFormScope' => 'company_cv_certification_save',
            'cvCertificationCsrfTokenId' => self::CSRF_CERTIFICATION_SAVE,
            'cvCertificationCustomizationSection' => CompanyCvCustomizationSectionKey::CERTIFICATION,
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
