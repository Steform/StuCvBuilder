<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCertificationOverrideScope;
use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\CvCertificationAdminUpdateService;
use App\Service\Cv\CvCertificationSettingsService;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Certification section customization (admin UI + public CV merge).
 */
class CompanyCvCertificationCustomizationService
{
    public const CSRF_CERTIFICATION_SAVE = 'employment_company_cv_certification';

    public const CSRF_CERTIFICATION_ENABLE = 'employment_company_cv_certification_enable';

    public const CSRF_CERTIFICATION_RESET = 'employment_company_cv_certification_reset';

    /**
     * @brief Wire company Certification customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvCertificationAdminUpdateService $cvCertificationAdminUpdateService Certification POST applier.
     * @param CvCertificationSettingsService $cvCertificationSettingsService Certification projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvCertificationAdminUpdateService $cvCertificationAdminUpdateService,
        private readonly CvCertificationSettingsService $cvCertificationSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Certification override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isCertificationCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::CERTIFICATION) !== null;
    }

    /**
     * @brief Merge company Certification override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function mergeCertificationOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::CERTIFICATION);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvCertificationOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvCertificationOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global Certification settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function enableCertificationCustomization(TrackedCompany $company): void
    {
        if ($this->isCertificationCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvCertificationOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                CertificationContract::KEY_ENTRIES => $this->buildInitialCanonicalEntriesFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::CERTIFICATION,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Certification override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resetCertificationToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::CERTIFICATION);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Certification admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function saveCertificationFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::CERTIFICATION);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.certification.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $payload = CompanyCvCertificationOverrideScope::decodeJson($override->getContentJson());
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

        $sanitized = CompanyCvCertificationOverrideScope::sanitizeForPersistence(
            $result['payload'],
            $activeLocales,
            $defaultLocale,
        );
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.certification.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Certification admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-06-11
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

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvCertificationSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::CERTIFICATION);
        $isCustomized = $override !== null;

        $certificationPayload = $isCustomized
            ? CompanyCvCertificationOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvCertificationOverrideScope::extractFromProfilePayload($globalPayload);

        if ($certificationPayload === [] && !$isCustomized) {
            $certificationPayload = [
                CertificationContract::KEY_ENTRIES => $globalResolved['canonicalEntries'],
            ];
        }

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

        $panelParam = $request->query->get('panel');
        $certificationPanel = is_string($panelParam) && $panelParam === 'certification_entries'
            ? 'certification_entries'
            : 'certification_entries';

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
            'cvCustomizationActivePanel' => $certificationPanel,
            'cvCertificationFormAction' => null,
            'cvCertificationFormScope' => 'company_cv_certification_save',
            'cvCertificationCsrfTokenId' => self::CSRF_CERTIFICATION_SAVE,
            'cvCertificationCustomizationSection' => CompanyCvCustomizationSectionKey::CERTIFICATION,
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
     * @brief Build initial canonical entries when global profile has no persisted Certification key yet.
     *
     * @param array<string, mixed> $globalPayload Global profile payload.
     * @return list<array<string, mixed>>
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function buildInitialCanonicalEntriesFromGlobal(array $globalPayload): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $contentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $resolved = $this->cvCertificationSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $defaultLocale,
        );

        return $resolved['canonicalEntries'];
    }
}
