<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\AboutPresentationTypographyContract;
use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Entity\TrackedCompany;
use App\Service\Cv\AboutPresentationContract;
use App\Service\Cv\CvAboutAdminUpdateService;
use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Cv\CvAboutPatternTemplateService;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Site\SiteColorsResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Per-company About section customization (admin UI + public CV merge).
 */
class CompanyCvAboutCustomizationService
{
    public const CSRF_ABOUT_SAVE = 'employment_company_cv_about';

    /**
     * @brief Wire company About customization dependencies.
     *
     * @param CompanyCvProfilePayloadService $companyCvProfilePayloadService Company/global CvProfile payload access.
     * @param CvAboutAdminUpdateService $cvAboutAdminUpdateService About POST applier.
     * @param CvAboutProfileSettingsService $cvAboutProfileSettingsService About projection service.
     * @param CvAboutPatternTemplateService $cvAboutPatternTemplateService Pattern templates.
     * @param SiteColorsResolver $siteColorsResolver Site accent colors for patterns.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @param TranslatorInterface $translator Translator for CKEditor UI JSON.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly CompanyCvProfilePayloadService $companyCvProfilePayloadService,
        private readonly CvAboutAdminUpdateService $cvAboutAdminUpdateService,
        private readonly CvAboutProfileSettingsService $cvAboutProfileSettingsService,
        private readonly CvAboutPatternTemplateService $cvAboutPatternTemplateService,
        private readonly SiteColorsResolver $siteColorsResolver,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly TranslatorInterface $translator,
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
    public function isAboutCustomized(TrackedCompany $company): bool
    {
        return $company->isCvContentCustom();
    }

    /**
     * @brief Apply About admin form onto the company custom CV profile.
     *
     * @param TrackedCompany $company Tracked company (must be in custom mode).
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function saveAboutFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        if (!$company->isCvContentCustom()) {
            $flashError[] = 'employment.companies.cv_customization.about.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        try {
            $payload = $this->companyCvProfilePayloadService->loadPayload($company);
            $result = $this->cvAboutAdminUpdateService->applyAboutImagesRequest($payload, $request, $activeLocales);
            $this->companyCvProfilePayloadService->savePayload($company, $result['payload']);

            $flashSuccess = array_merge($flashSuccess, $result['flashSuccess']);
            $flashWarning = array_merge($flashWarning, $result['flashWarning']);
            $flashSuccess[] = 'employment.companies.cv_customization.about.flash.saved';
        } catch (\InvalidArgumentException $exception) {
            $flashWarning[] = $exception->getMessage();
        }

        return compact('flashSuccess', 'flashWarning', 'flashError');
    }

    /**
     * @brief Build Twig variables for company About admin panel.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request for locale and panel state.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function buildAboutAdminViewData(TrackedCompany $company, Request $request): array
    {
        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $globalPayload = $this->loadGlobalPayload();
        $isCustomized = $company->isCvContentCustom();
        $aboutPayload = $isCustomized
            ? $this->companyCvProfilePayloadService->loadPayload($company)
            : $globalPayload;

        $contentJson = json_encode($aboutPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestLocale = (string) $request->getLocale();

        $profilePhotoSettings = $this->cvAboutProfileSettingsService->resolveFromContentJson(
            is_string($contentJson) ? $contentJson : '{}',
            $activeLocales,
            $defaultLocale,
            $requestLocale,
            false
        );

        $profilePayloadForCss = SectionBackgroundContract::applyNormalizedMapToPayload($aboutPayload);
        $patternConfig = AboutSectionPatternCustomizationContract::fromPayload($profilePayloadForCss);
        $patternConfig = $this->siteColorsResolver->applyAccentToPattern($patternConfig);
        $patternLeftResolved = $this->cvAboutPatternTemplateService->renderTemplate($patternConfig['patternLeftId'] ?? null);
        $patternRightResolved = $this->cvAboutPatternTemplateService->renderTemplate($patternConfig['patternRightId'] ?? null);

        $panelParam = $request->query->get('panel');
        $aboutPanel = is_string($panelParam) && in_array($panelParam, ['section', 'photo', 'presentation'], true)
            ? $panelParam
            : 'section';
        $localeParam = $request->query->get('locale');
        $aboutLocale = is_string($localeParam) && in_array($localeParam, $activeLocales, true)
            ? $localeParam
            : $defaultLocale;

        $globalPhotoSettings = $this->cvAboutProfileSettingsService->resolveFromContentJson(
            json_encode($globalPayload, JSON_UNESCAPED_UNICODE) ?: '{}',
            $activeLocales,
            $defaultLocale,
            $requestLocale,
            false
        );

        return [
            'cvAboutCustomizationEnabled' => $isCustomized,
            'cvAboutInheritedSummaryPhotoPath' => $globalPhotoSettings['path'],
            'cvAboutInheritedPresentation' => $globalPhotoSettings['presentation'],
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
            'cvEditorPlaceholderUiJson' => $this->buildCkeditorPlaceholderUiJson($requestLocale),
            'cvAboutBackground' => $profilePhotoSettings['background'],
            'cvAboutProfilePhotoPath' => $profilePhotoSettings['path'],
            'cvAboutProfilePhotoHasUserUpload' => $profilePhotoSettings['hasUserProfilePhoto'],
            'cvAboutPresentation' => $profilePhotoSettings['presentation'],
            'cvAboutPresentationTypographyForm' => $this->buildAboutPresentationTypographyFormRows(
                AboutPresentationTypographyContract::fromPayload($profilePayloadForCss)
            ),
            'cvAboutSectionPattern' => $patternConfig,
            'cvAboutPatternTemplateLeftSvg' => $patternLeftResolved['svg'],
            'cvAboutPatternTemplateRightSvg' => $patternRightResolved['svg'],
            'cvAboutPatternTemplatesBySide' => $this->cvAboutPatternTemplateService->listPatternChoicesBySide(),
            'cvAboutPatternAllowedColors' => $this->cvAboutPatternTemplateService->getAllowedHexPalette(),
            'cvAboutPatternWarnings' => array_values(array_unique(array_merge(
                $patternLeftResolved['warnings'],
                $patternRightResolved['warnings']
            ))),
            'cvAboutPatternCssCacheSuffix' => $this->siteColorsResolver->patternCssCacheSuffix($profilePayloadForCss),
            'cvAboutProfileCssCacheSuffix' => AboutPresentationContract::stylesheetCacheSuffixFromPayload(
                $profilePayloadForCss,
                (int) ($company->getId() ?? 0)
            ),
            'cvAboutPreviewByLocale' => $this->cvAboutProfileSettingsService->buildAdminPreviewPayloadByLocale(
                is_string($contentJson) ? $contentJson : '{}',
                $activeLocales,
                $defaultLocale,
                $patternLeftResolved['svg'],
                $patternRightResolved['svg']
            ),
            'cvCustomizationActivePanel' => $aboutPanel,
            'cvCustomizationActiveLocale' => $aboutLocale,
            'profilePhotoPlaceholderPath' => CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH,
            'cvAboutFormAction' => null,
            'cvAboutFormScope' => 'company_cv_about_save',
            'cvAboutCsrfTokenId' => self::CSRF_ABOUT_SAVE,
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

    /**
     * @brief Build CKEditor placeholder picker JSON for company About forms.
     *
     * @param string $requestLocale Admin UI locale.
     * @return string JSON for `data-cv-placeholder-ui`.
     * @date 2026-06-01
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

    /**
     * @brief Build typography admin rows for About presentation form.
     *
     * @param array<string, string> $typography Normalized typography map.
     * @return array<string, array{value: string, unit: string}>
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildAboutPresentationTypographyFormRows(array $typography): array
    {
        $rows = [];
        foreach (AboutPresentationTypographyContract::ELEMENT_KEYS as $elementKey) {
            $fontSize = $typography[$elementKey] ?? AboutPresentationTypographyContract::DEFAULTS[$elementKey]['value']
                .AboutPresentationTypographyContract::DEFAULTS[$elementKey]['unit'];
            $rows[$elementKey] = AboutPresentationTypographyContract::splitFontSize($fontSize, $elementKey);
        }

        return $rows;
    }
}
