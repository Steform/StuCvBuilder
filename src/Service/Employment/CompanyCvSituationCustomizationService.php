<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CompanyCvSituationOverrideScope;
use App\Cv\CvProfilePersistenceScope;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvSituationContentAdminUpdateService;
use App\Service\Cv\CvSituationContentSettingsService;
use App\Service\Cv\SituationContentContract;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Per-company Situation section customization (admin UI + public CV merge).
 */
class CompanyCvSituationCustomizationService
{
    public const CSRF_SITUATION_SAVE = 'employment_company_cv_situation';

    public const CSRF_SITUATION_ENABLE = 'employment_company_cv_situation_enable';

    public const CSRF_SITUATION_RESET = 'employment_company_cv_situation_reset';

    /**
     * @brief Wire company Situation customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvSituationContentAdminUpdateService $cvSituationContentAdminUpdateService Situation POST applier.
     * @param CvSituationContentSettingsService $cvSituationContentSettingsService Situation projection service.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvSituationContentAdminUpdateService $cvSituationContentAdminUpdateService,
        private readonly CvSituationContentSettingsService $cvSituationContentSettingsService,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Whether the company has a persisted Situation override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isSituationCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SITUATION) !== null;
    }

    /**
     * @brief Merge company Situation override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function mergeSituationOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SITUATION);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvSituationOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvSituationOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global Situation settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function enableSituationCustomization(TrackedCompany $company): void
    {
        if ($this->isSituationCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvSituationOverrideScope::extractFromProfilePayload($globalPayload);
        if ($slice === []) {
            $slice = [
                SituationContentContract::KEY_CONTENT_BY_LOCALE => $this->buildInitialContentMapFromGlobal($globalPayload),
            ];
        }

        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::SITUATION,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company Situation override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resetSituationToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SITUATION);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply Situation admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveSituationFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SITUATION);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.situation.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        $payload = CompanyCvSituationOverrideScope::decodeJson($override->getContentJson());
        $result = $this->cvSituationContentAdminUpdateService->applySituationContentFromRequest($payload, $request, $activeLocales);

        $flashSuccess = array_merge($flashSuccess, $result['flashSuccess']);
        $flashWarning = array_merge($flashWarning, $result['flashWarning']);
        $flashError = array_merge($flashError, $result['flashError']);

        if ($flashError !== []) {
            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $sanitized = CompanyCvSituationOverrideScope::sanitizeForPersistence($result['payload']);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();

        $flashSuccess[] = 'employment.companies.cv_customization.situation.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company Situation admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale state.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function buildSituationAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $globalResolved = $this->cvSituationContentSettingsService->resolveFromContentJson(
            $globalContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::SITUATION);
        $isCustomized = $override !== null;

        $situationPayload = $isCustomized
            ? CompanyCvSituationOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvSituationOverrideScope::extractFromProfilePayload($globalPayload);

        if ($situationPayload === [] && !$isCustomized) {
            $situationPayload = [
                SituationContentContract::KEY_CONTENT_BY_LOCALE => $globalResolved['contentByLocale'],
            ];
        }

        $overrideContentJson = json_encode($situationPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $overrideResolved = $this->cvSituationContentSettingsService->resolveFromContentJson(
            $overrideContentJson,
            $activeLocales,
            $defaultLocale,
            (string) $request->getLocale(),
        );

        $localeParam = $request->query->get('locale');
        $situationLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        return [
            'cvSituationCustomizationEnabled' => $isCustomized,
            'cvSituationInheritedPreview' => $globalResolved['contentByLocale'][$defaultLocale] ?? $globalResolved['content'],
            'cvSituationContentByLocale' => $overrideResolved['contentByLocale'],
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $situationLocale,
            'cvSituationFormAction' => null,
            'cvSituationFormScope' => 'company_cv_situation_save',
            'cvSituationCsrfTokenId' => self::CSRF_SITUATION_SAVE,
            'cvSituationCustomizationSection' => CompanyCvCustomizationSectionKey::SITUATION,
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
     * @brief Build initial locale map when global profile has no persisted Situation key yet.
     *
     * @param array<string, mixed> $globalPayload Global profile payload.
     * @return array<string, array<string, mixed>>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildInitialContentMapFromGlobal(array $globalPayload): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $contentJson = json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $resolved = $this->cvSituationContentSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $defaultLocale,
        );

        $map = [];
        foreach ($resolved['contentByLocale'] as $locale => $row) {
            if (!is_string($locale) || !is_array($row)) {
                continue;
            }

            $normalized = SituationContentContract::normalizeContentRow($row);
            if ($normalized !== null) {
                $map[$locale] = $normalized;
            }
        }

        return $map;
    }
}
