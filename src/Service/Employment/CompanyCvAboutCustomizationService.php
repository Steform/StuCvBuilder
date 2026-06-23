<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\AboutPresentationTypographyContract;
use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\CompanyCvAboutOverrideScope;
use App\Cv\CompanyCvCustomizationSectionKey;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CvProfileRepository;
use App\Service\Cv\AboutPresentationContract;
use App\Service\Cv\CvAboutAdminUpdateService;
use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Cv\CvAboutPatternTemplateService;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Site\SiteColorsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Per-company About section customization (admin UI + public CV merge).
 */
class CompanyCvAboutCustomizationService
{
    public const CSRF_ABOUT_SAVE = 'employment_company_cv_about';

    public const CSRF_ABOUT_ENABLE = 'employment_company_cv_about_enable';

    public const CSRF_ABOUT_RESET = 'employment_company_cv_about_reset';

    /**
     * @brief Wire company About customization dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CompanyCvSectionOverrideRepository $overrideRepository Override repository.
     * @param CvProfileRepository $cvProfileRepository Global CV profile repository.
     * @param CvAboutAdminUpdateService $cvAboutAdminUpdateService About POST applier.
     * @param CvAboutProfileSettingsService $cvAboutProfileSettingsService About projection service.
     * @param CvAboutPatternTemplateService $cvAboutPatternTemplateService Pattern templates.
     * @param SiteColorsResolver $siteColorsResolver Site accent colors for patterns.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration.
     * @param TranslatorInterface $translator Translator for CKEditor UI JSON.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyCvSectionOverrideRepository $overrideRepository,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvAboutAdminUpdateService $cvAboutAdminUpdateService,
        private readonly CvAboutProfileSettingsService $cvAboutProfileSettingsService,
        private readonly CvAboutPatternTemplateService $cvAboutPatternTemplateService,
        private readonly SiteColorsResolver $siteColorsResolver,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @brief Whether the company has a persisted About override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function isAboutCustomized(TrackedCompany $company): bool
    {
        return $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::ABOUT) !== null;
    }

    /**
     * @brief Merge company About override into resolved CV payload when present.
     *
     * @param array<string, mixed> $payload Default CV payload after global resolve steps.
     * @param TrackedCompany|null $company Active tracked company or null.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function mergeAboutOverrideIntoPayload(array $payload, ?TrackedCompany $company): array
    {
        if ($company === null) {
            return $payload;
        }

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::ABOUT);
        if ($override === null) {
            return $payload;
        }

        $overridePayload = CompanyCvAboutOverrideScope::decodeJson($override->getContentJson());

        return CompanyCvAboutOverrideScope::mergeIntoPayload($payload, $overridePayload);
    }

    /**
     * @brief Copy global About settings into a new company override row.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function enableAboutCustomization(TrackedCompany $company): void
    {
        if ($this->isAboutCustomized($company)) {
            return;
        }

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $slice = CompanyCvAboutOverrideScope::extractFromProfilePayload($globalPayload);
        $json = json_encode($slice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::ABOUT,
            is_string($json) ? $json : '{}',
        );
        $this->entityManager->persist($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Remove company About override (revert to global CV).
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resetAboutToInherited(TrackedCompany $company): void
    {
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::ABOUT);
        if ($override === null) {
            return;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();
    }

    /**
     * @brief Apply About admin form for a company override.
     *
     * @param TrackedCompany $company Tracked company.
     * @param Request $request HTTP request.
     * @return array{flashSuccess: list<string>, flashWarning: list<string>, flashError: list<string>}
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function saveAboutFromRequest(TrackedCompany $company, Request $request): array
    {
        $flashSuccess = [];
        $flashWarning = [];
        $flashError = [];

        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::ABOUT);
        if ($override === null) {
            $flashError[] = 'employment.companies.cv_customization.about.flash.not_enabled';

            return compact('flashSuccess', 'flashWarning', 'flashError');
        }

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];

        try {
            $payload = CompanyCvAboutOverrideScope::decodeJson($override->getContentJson());
            $result = $this->cvAboutAdminUpdateService->applyAboutImagesRequest($payload, $request, $activeLocales);
            $sanitized = CompanyCvAboutOverrideScope::sanitizeForPersistence($result['payload']);
            $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $override->setContentJson(is_string($json) ? $json : '{}');
            $this->entityManager->flush();

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
     * @date 2026-06-01
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

        $globalPayload = $this->loadLatestGlobalProfilePayload();
        $override = $this->overrideRepository->findOneForCompanySection($company, CompanyCvCustomizationSectionKey::ABOUT);
        $isCustomized = $override !== null;

        $aboutPayload = $isCustomized
            ? CompanyCvAboutOverrideScope::decodeJson($override->getContentJson())
            : CompanyCvAboutOverrideScope::extractFromProfilePayload($globalPayload);

        $contentJson = json_encode($aboutPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requestLocale = (string) $request->getLocale();

        $profilePhotoSettings = $this->cvAboutProfileSettingsService->resolveFromContentJson(
            is_string($contentJson) ? $contentJson : '{}',
            $activeLocales,
            $defaultLocale,
            $requestLocale,
            false
        );

        $profilePayloadForCss = CompanyCvAboutOverrideScope::sanitizeForPersistence($aboutPayload);
        $profilePayloadForCss = SectionBackgroundContract::applyNormalizedMapToPayload($profilePayloadForCss);
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
            json_encode(CompanyCvAboutOverrideScope::extractFromProfilePayload($globalPayload), JSON_UNESCAPED_UNICODE) ?: '{}',
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
