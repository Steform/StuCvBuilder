<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CvPencilDecorationContract;
use App\Cv\CvProfilePersistenceScope;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvPublicIdentityAdminService;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Site\SiteColorsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Per-company CV data (public identity / page titles / pencil) customization.
 */
class CompanyCvDataCustomizationService
{
    public const CSRF_CV_DATA_SAVE = 'employment_company_cv_data';

    /**
     * @brief Wire company CV data customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvPublicIdentityAdminService $cvPublicIdentityAdminService Identity extract/parse helpers.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @param SiteColorsResolver $siteColorsResolver Site accent for pencil help text.
     * @param EmploymentDocumentStorageService $employmentDocumentStorageService PDF stamp cache purge.
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param TranslatorInterface $translator Translator for CKEditor UI JSON.
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvPublicIdentityAdminService $cvPublicIdentityAdminService,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly SiteColorsResolver $siteColorsResolver,
        private readonly EmploymentDocumentStorageService $employmentDocumentStorageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief Whether the company uses an independent CV clone (custom mode).
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function isCvDataCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply CV data admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function saveCvDataFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.cv_data.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null)
            ? $localeConfig['defaultLocale']
            : ($activeLocales[0] ?? 'fr');

        $submittedPageTitles = $request->request->all('page_title');
        $pageTitleByLocale = $this->normalizePageTitleByLocale(
            is_array($submittedPageTitles) ? $submittedPageTitles : [],
            $activeLocales
        );
        if ($pageTitleByLocale === []) {
            $flashWarning[] = 'dashboard.customization_cv.flash.empty_page_title';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $payload = $this->companyCvProfilePayloadService->loadPayload($company);
        $existingContentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $existingIdentity = $this->cvPublicIdentityAdminService->extractStoredIdentityMap(
            $existingContentJson !== '' ? $existingContentJson : '{}'
        );
        $identityPayload = $this->cvPublicIdentityAdminService->parseFromCvDataRequest(
            $request,
            $activeLocales,
            $existingIdentity
        );
        if ($identityPayload === null) {
            $flashWarning[] = 'dashboard.cv_public_identity.flash.invalid';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $existingBirthDate = $existingIdentity[CvPublicIdentityContract::FIELD_BIRTH_DATE] ?? null;
        $existingBirthDate = is_string($existingBirthDate) ? trim($existingBirthDate) : '';

        $payload['pageTitleByLocale'] = $pageTitleByLocale;
        $payload[CvPublicIdentityContract::KEY_ROOT] = $identityPayload;
        $payload = CvPencilDecorationContract::mergeSubmittedFromCvDataRequest($payload, $request);

        $this->companyCvProfilePayloadService->savePayload($company, $payload);

        $defaultLocalizedTitle = $pageTitleByLocale[$defaultLocale] ?? reset($pageTitleByLocale) ?: '';
        $profile = $this->companyCvProfilePayloadService->requireCustomProfile($company);
        if (is_string($defaultLocalizedTitle) && $defaultLocalizedTitle !== '') {
            $profile->setTitle($defaultLocalizedTitle);
            $this->entityManager->flush();
        }

        $newBirthDate = $identityPayload[CvPublicIdentityContract::FIELD_BIRTH_DATE] ?? null;
        $newBirthDate = is_string($newBirthDate) ? trim($newBirthDate) : '';
        if ($newBirthDate !== $existingBirthDate) {
            $this->employmentDocumentStorageService->purgeAllStampedPdfCaches();
        }

        $flashSuccess[] = 'employment.companies.cv_customization.cv_data.flash.saved';

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company CV data admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale state.
     * @return array<string, mixed>
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function buildCvDataAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null)
            ? $localeConfig['defaultLocale']
            : ($activeLocales[0] ?? 'fr');

        $isCustomized = $company->isCvContentCustom();
        $globalPayload = CvProfilePersistenceScope::sanitizeForPersistence(
            $this->companyCvProfilePayloadService->loadGlobalPayload()
        );
        $payload = $isCustomized
            ? CvProfilePersistenceScope::sanitizeForPersistence(
                $this->companyCvProfilePayloadService->loadPayload($company)
            )
            : $globalPayload;

        $contentJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contentJsonString = is_string($contentJson) ? $contentJson : '{}';
        $globalContentJson = json_encode($globalPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $globalContentJsonString = is_string($globalContentJson) ? $globalContentJson : '{}';

        $localeParam = $request->query->get('locale');
        $activeLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        $requestLocale = (string) $request->getLocale();
        $inheritedIdentity = $this->cvPublicIdentityAdminService->extractForAdmin(
            $globalContentJsonString,
            $activeLocales
        );

        return [
            'cvDataCustomizationEnabled' => $isCustomized,
            'cvDataInheritedIdentity' => $inheritedIdentity,
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvCustomizationActiveLocale' => $activeLocale,
            'cvPageTitleByLocale' => $this->extractPageTitleByLocale($contentJsonString, $activeLocales),
            'cvPublicIdentity' => $this->cvPublicIdentityAdminService->extractForAdmin(
                $contentJsonString,
                $activeLocales
            ),
            'cvPencilDecoration' => CvPencilDecorationContract::fromPayload($payload),
            'siteAccentColor' => $this->siteColorsResolver->resolveAccentColor(),
            'cvEditorPlaceholderUiJson' => $this->buildCkeditorPlaceholderUiJson($requestLocale),
            'cvDataFormAction' => null,
            'cvDataFormScope' => 'company_cv_cv_data_save',
            'cvDataCsrfTokenId' => self::CSRF_CV_DATA_SAVE,
        ];
    }

    /**
     * @brief Keep only non-empty localized page titles for allowed locales.
     *
     * @param array<string, mixed> $submittedPageTitles Raw submitted page titles by locale.
     * @param list<string> $activeLocales Allowed active locales.
     * @return array<string, string>
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function normalizePageTitleByLocale(array $submittedPageTitles, array $activeLocales): array
    {
        $normalizedTitles = [];
        foreach ($activeLocales as $localeCode) {
            $rawTitle = $submittedPageTitles[$localeCode] ?? '';
            if (!is_string($rawTitle)) {
                continue;
            }

            $normalizedTitle = trim($rawTitle);
            if ($normalizedTitle === '') {
                continue;
            }

            $normalizedTitles[$localeCode] = $normalizedTitle;
        }

        return $normalizedTitles;
    }

    /**
     * @brief Extract localized page titles from profile payload for active locales.
     *
     * @param string $contentJson Profile JSON payload.
     * @param list<string> $activeLocales Allowed active locales.
     * @return array<string, string>
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function extractPageTitleByLocale(string $contentJson, array $activeLocales): array
    {
        $decoded = json_decode($contentJson, true);
        $payload = is_array($decoded) ? $decoded : [];
        $rawTitles = is_array($payload['pageTitleByLocale'] ?? null) ? $payload['pageTitleByLocale'] : [];
        $titles = [];
        foreach ($activeLocales as $localeCode) {
            $rawTitle = $rawTitles[$localeCode] ?? '';
            if (!is_string($rawTitle)) {
                continue;
            }

            $normalizedTitle = trim($rawTitle);
            if ($normalizedTitle === '') {
                continue;
            }

            $titles[$localeCode] = $normalizedTitle;
        }

        return $titles;
    }

    /**
     * @brief Build CKEditor placeholder picker JSON for company CV data forms.
     *
     * @param string $requestLocale Admin UI locale.
     * @return string JSON for `data-cv-placeholder-ui`.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function buildCkeditorPlaceholderUiJson(string $requestLocale): string
    {
        $ckeditorPlaceholderTokens = [];
        foreach (CvPublicIdentityContract::PLACEHOLDER_TOKEN_NAMES as $name) {
            $insert = '[[cv.'.$name.']]';
            $labelKey = 'dashboard.cv_public_identity.ckeditor.token_'.$name;
            $label = $this->translator->trans($labelKey, ['%token%' => $insert], 'messages', $requestLocale);
            if ($label === $labelKey) {
                $label = $this->translator->trans(
                    'dashboard.cv_public_identity.ckeditor.insert_token',
                    ['%token%' => $insert],
                    'messages',
                    $requestLocale
                );
            }
            $ckeditorPlaceholderTokens[] = [
                'insert' => $insert,
                'label' => $label,
            ];
        }

        return json_encode([
            'pickerLabel' => $this->translator->trans('dashboard.cv_public_identity.ckeditor.picker_label', [], 'messages', $requestLocale),
            'menuAria' => $this->translator->trans('dashboard.cv_public_identity.ckeditor.menu_aria', [], 'messages', $requestLocale),
            'tokens' => $ckeditorPlaceholderTokens,
        ], JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
