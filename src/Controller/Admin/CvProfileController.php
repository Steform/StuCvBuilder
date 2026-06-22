<?php

namespace App\Controller\Admin;

use App\Cv\AboutPresentationTypographyContract;
use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\CvPencilDecorationContract;
use App\Cv\CvProfilePersistenceScope;
use App\Cv\SectionBackgroundContract;
use App\Cv\SectionTransitionContract;
use App\Cv\SituationBackgroundTexture;
use App\Entity\CvProfile;
use App\Repository\CvProfileRepository;
use App\Service\Cv\AboutPresentationContract;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Customization\CustomizationUiStateResolver;
use App\Service\Site\SiteColorsResolver;
use App\Service\Cv\CvAboutAdminUpdateService;
use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Cv\CvAboutPatternTemplateService;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\CvCertificationAdminUpdateService;
use App\Service\Cv\CvCertificationSettingsService;
use App\Service\Cv\CvEducationAdminUpdateService;
use App\Service\Cv\CvEducationSettingsService;
use App\Service\Cv\CvExperienceAdminUpdateService;
use App\Service\Cv\CvExperienceSettingsService;
use App\Service\Cv\CvFlagshipProjectsAdminUpdateService;
use App\Service\Cv\CvFlagshipProjectsSettingsService;
use App\Service\Cv\CvInterestsAdminUpdateService;
use App\Service\Cv\CvInterestsSettingsService;
use App\Service\Cv\InterestsContract;
use App\Service\Cv\CvLanguagesAdminUpdateService;
use App\Service\Cv\CvLanguagesSettingsService;
use App\Service\Cv\CvReferencesAdminUpdateService;
use App\Service\Cv\CvReferencesSettingsService;
use App\Service\Cv\CvWebProfilesAdminUpdateService;
use App\Service\Cv\CvWebProfilesSettingsService;
use App\Service\Employment\EmploymentDocumentStorageService;
use App\Service\Cv\CvPublicIdentityAdminService;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Cv\CvSituationContentAdminUpdateService;
use App\Service\Cv\CvSituationContentSettingsService;
use App\Service\Cv\CvSkillsSettingsService;
use App\Service\Cv\EducationContract;
use App\Service\Cv\ExperienceContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\ReferencesContract;
use App\Service\Cv\SituationContentContract;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Setup\SiteSetupOnboardingService;
use App\Service\Util\ImageReencoder;
use App\Service\RichText\RichHtmlSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller CvProfileController.
 */
class CvProfileController extends AbstractController
{

    /**
     * @brief Wire persistence, CV customization services, and translator for JSON i18n payloads.
     *
     * @param EntityManagerInterface $entityManager ORM flush boundary.
     * @param CvProfileRepository $cvProfileRepository Latest CV profile row access.
     * @param LocaleConfigurationService $localeConfigurationService Active locales and default locale.
     * @param CvAboutProfileSettingsService $cvAboutProfileSettingsService About JSON projection for forms.
     * @param CvAboutPatternTemplateService $cvAboutPatternTemplateService About SVG pattern templates and color-token conversion.
     * @param CvExperienceSettingsService $cvExperienceSettingsService Experience JSON projection for forms and preview.
     * @param CvSituationContentAdminUpdateService $cvSituationContentAdminUpdateService Situation POST applier for admin forms.
     * @param RichHtmlSanitizer $richHtmlSanitizer Presentation HTML sanitization on save.
     * @param TranslatorInterface $translator User-visible strings for CKEditor placeholder UI JSON.
     * @param CvPublicIdentityAdminService $cvPublicIdentityAdminService Public CV identity JSON for the cv_data tab.
     * @param EmploymentDocumentStorageService $employmentDocumentStorageService Employment PDF stamped cache storage.
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CvAboutProfileSettingsService $cvAboutProfileSettingsService,
        private readonly CvAboutPatternTemplateService $cvAboutPatternTemplateService,
        private readonly CvAboutAdminUpdateService $cvAboutAdminUpdateService,
        private readonly CvExperienceSettingsService $cvExperienceSettingsService,
        private readonly CvExperienceAdminUpdateService $cvExperienceAdminUpdateService,
        private readonly CvEducationSettingsService $cvEducationSettingsService,
        private readonly CvEducationAdminUpdateService $cvEducationAdminUpdateService,
        private readonly CvCertificationSettingsService $cvCertificationSettingsService,
        private readonly CvCertificationAdminUpdateService $cvCertificationAdminUpdateService,
        private readonly CvSituationContentSettingsService $cvSituationContentSettingsService,
        private readonly CvSituationContentAdminUpdateService $cvSituationContentAdminUpdateService,
        private readonly CvSkillsSettingsService $cvSkillsSettingsService,
        private readonly CvFlagshipProjectsSettingsService $cvFlagshipProjectsSettingsService,
        private readonly CvFlagshipProjectsAdminUpdateService $cvFlagshipProjectsAdminUpdateService,
        private readonly CvLanguagesSettingsService $cvLanguagesSettingsService,
        private readonly CvLanguagesAdminUpdateService $cvLanguagesAdminUpdateService,
        private readonly CvInterestsSettingsService $cvInterestsSettingsService,
        private readonly CvInterestsAdminUpdateService $cvInterestsAdminUpdateService,
        private readonly CvWebProfilesSettingsService $cvWebProfilesSettingsService,
        private readonly CvWebProfilesAdminUpdateService $cvWebProfilesAdminUpdateService,
        private readonly CvReferencesSettingsService $cvReferencesSettingsService,
        private readonly CvReferencesAdminUpdateService $cvReferencesAdminUpdateService,
        private readonly CustomizationPlaceholderStateService $placeholderStateService,
        private readonly CustomizationUiStateResolver $customizationUiStateResolver,
        private readonly SiteColorsResolver $siteColorsResolver,
        private readonly CvPublicIdentityAdminService $cvPublicIdentityAdminService,
        private readonly EmploymentDocumentStorageService $employmentDocumentStorageService,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly TranslatorInterface $translator,
        private readonly SiteSetupOnboardingService $siteSetupOnboardingService,
        private readonly ImageReencoder $imageReencoder,
    ) {
    }
    /**
     * @brief Render and handle CV customization dashboard including localized page title and About settings; passes stored About atmosphere style to the admin form for radio preselection.
     * @param Request $request HTTP request including optional `tab` query for active Bootstrap tab slug.
     * @return Response HTML customization dashboard or redirect after POST handling.
     * @date 2026-05-28
     * @author Stephane H.
     */
    #[Route('/admin/cv', name: 'admin_cv_index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CV_EDIT')]
    public function index(Request $request): Response
    {
        $localeConfiguration = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($localeConfiguration['activeLocales'] ?? null) ? $localeConfiguration['activeLocales'] : [];
        if ($activeLocales === []) {
            $activeLocales = $this->localeConfigurationService->getSupportedLocales();
        }
        $defaultLocale = is_string($localeConfiguration['defaultLocale'] ?? null) ? $localeConfiguration['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        if ($request->isMethod('POST')) {
            $formScope = (string) $request->request->get('form_scope', '');
            if ($formScope === 'cv_data' || $formScope === 'page_title') {
                return $this->handleCvDataUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'about_images') {
                return $this->handleAboutImagesUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'experience') {
                return $this->handleExperienceUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'education') {
                return $this->handleEducationUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'certification') {
                return $this->handleCertificationUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'situation_content') {
                return $this->handleSituationContentUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'flagship_projects') {
                return $this->handleFlagshipProjectsUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'languages') {
                return $this->handleLanguagesUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'interests') {
                return $this->handleInterestsUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'interests_layout') {
                return $this->handleInterestsLayoutUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'web_profiles') {
                return $this->handleWebProfilesUpdate($request, $activeLocales, $defaultLocale);
            }

            if ($formScope === 'references') {
                return $this->handleReferencesUpdate($request, $activeLocales, $defaultLocale);
            }

            $backgroundSectionKey = $this->resolveSectionKeyFromFormScope($formScope, '_background');
            if ($backgroundSectionKey !== null) {
                return $this->handleSectionBackgroundUpdate($request, $activeLocales, $defaultLocale, $backgroundSectionKey);
            }

        }

        $latestProfile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if ($latestProfile instanceof CvProfile) {
            $this->migrateProfilePayloadIfNeeded($latestProfile);
        }

        $profileContentJson = $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}';
        $cvPageTitleByLocale = $this->extractPageTitleByLocale($profileContentJson, $activeLocales);
        $cvPublicIdentity = $this->cvPublicIdentityAdminService->extractForAdmin($profileContentJson, $activeLocales);
        $profilePhotoSettings = $this->cvAboutProfileSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale(),
            false
        );

        $experienceResolved = $this->cvExperienceSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $educationResolved = $this->cvEducationSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $certificationResolved = $this->cvCertificationSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $situationContentResolved = $this->cvSituationContentSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $skillsResolved = $this->cvSkillsSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $flagshipProjectsResolved = $this->cvFlagshipProjectsSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $languagesResolved = $this->cvLanguagesSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $interestsResolved = $this->cvInterestsSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $webProfilesResolved = $this->cvWebProfilesSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $request->getLocale()
        );

        $referencesResolved = $this->cvReferencesSettingsService->resolveFromContentJson(
            $latestProfile instanceof CvProfile ? $latestProfile->getContentJson() : '{}',
            $activeLocales,
            $defaultLocale,
            $request->getLocale()
        );

        $cvUiState = $this->customizationUiStateResolver->resolveCvFromRequest($request, $activeLocales, $defaultLocale);

        $tabQuery = $request->query->get('tab');
        $panelQuery = $request->query->get('panel');
        if (
            is_string($tabQuery)
            && trim($tabQuery) === 'experience'
            && (!is_string($panelQuery) || trim($panelQuery) === '')
        ) {
            return $this->redirectToRoute(
                'admin_cv_index',
                $this->customizationUiStateResolver->buildCvRedirectParams($cvUiState)
            );
        }

        if (
            is_string($tabQuery)
            && trim($tabQuery) === 'situation'
        ) {
            return $this->redirectToRoute(
                'admin_cv_index',
                $this->customizationUiStateResolver->buildCvRedirectParams(
                    $this->customizationUiStateResolver->resolveCvState(
                        'about',
                        is_string($panelQuery) && trim($panelQuery) !== '' ? trim($panelQuery) : 'situation_content',
                        $request->query->get('locale'),
                        $activeLocales,
                        $defaultLocale,
                    )
                )
            );
        }

        if (
            is_string($tabQuery)
            && trim($tabQuery) === 'certification'
            && (!is_string($panelQuery) || trim($panelQuery) === '')
        ) {
            return $this->redirectToRoute(
                'admin_cv_index',
                $this->customizationUiStateResolver->buildCvRedirectParams($cvUiState)
            );
        }

        foreach (['languages', 'interests', 'web_profiles', 'references'] as $defaultPanelTab) {
            if (
                is_string($tabQuery)
                && trim($tabQuery) === $defaultPanelTab
                && (!is_string($panelQuery) || trim($panelQuery) === '')
            ) {
                return $this->redirectToRoute(
                    'admin_cv_index',
                    $this->customizationUiStateResolver->buildCvRedirectParams($cvUiState)
                );
            }
        }

        $requestLocale = (string) $request->getLocale();
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

        $cvEditorPlaceholderUiJson = json_encode([
            'pickerLabel' => $this->translator->trans('dashboard.cv_public_identity.ckeditor.picker_label', [], 'messages', $requestLocale),
            'menuAria' => $this->translator->trans('dashboard.cv_public_identity.ckeditor.menu_aria', [], 'messages', $requestLocale),
            'tokens' => $ckeditorPlaceholderTokens,
        ], JSON_UNESCAPED_UNICODE);

        $profilePayloadForCss = $latestProfile instanceof CvProfile
            ? CvProfilePersistenceScope::sanitizeForPersistence($this->decodeJsonPayload($latestProfile->getContentJson()))
            : [];

        $profilePayloadForCss = SectionBackgroundContract::applyNormalizedMapToPayload($profilePayloadForCss);
        $patternConfig = AboutSectionPatternCustomizationContract::fromPayload($profilePayloadForCss);
        $patternConfig = $this->siteColorsResolver->applyAccentToPattern($patternConfig);
        $patternLeftResolved = $this->cvAboutPatternTemplateService->renderTemplate($patternConfig['patternLeftId'] ?? null);
        $patternRightResolved = $this->cvAboutPatternTemplateService->renderTemplate($patternConfig['patternRightId'] ?? null);
        $cvSectionBackgrounds = is_array($profilePayloadForCss[SectionBackgroundContract::KEY] ?? null)
            ? $profilePayloadForCss[SectionBackgroundContract::KEY]
            : SectionBackgroundContract::normalizeMap(null, $profilePayloadForCss);
        $experienceTexture = SituationBackgroundTexture::fromStored(
            $cvSectionBackgrounds['experience']['texture'] ?? $profilePayloadForCss['experienceBackgroundTexture'] ?? null
        );
        $educationTexture = SituationBackgroundTexture::fromStored(
            $cvSectionBackgrounds['education']['texture'] ?? $profilePayloadForCss['educationBackgroundTexture'] ?? null
        );
        $certificationTexture = SituationBackgroundTexture::fromStored(
            $cvSectionBackgrounds['certification']['texture'] ?? $profilePayloadForCss['certificationBackgroundTexture'] ?? null
        );
        $languagesTexture = SituationBackgroundTexture::fromStored(
            $cvSectionBackgrounds['languages']['texture'] ?? $profilePayloadForCss['languagesBackgroundTexture'] ?? null
        );
        $interestsTexture = SituationBackgroundTexture::fromStored(
            $cvSectionBackgrounds['interests']['texture'] ?? $profilePayloadForCss['interestsBackgroundTexture'] ?? null
        );
        $webProfilesTexture = SituationBackgroundTexture::fromStored(
            $cvSectionBackgrounds['web_profiles']['texture'] ?? $profilePayloadForCss['webProfilesBackgroundTexture'] ?? null
        );
        $referencesTexture = SituationBackgroundTexture::fromStored(
            $cvSectionBackgrounds['references']['texture'] ?? $profilePayloadForCss['referencesBackgroundTexture'] ?? null
        );
        return $this->render('admin/cv/index.html.twig', [
            'activeLocales' => $activeLocales,
            'cvPageTitleByLocale' => $cvPageTitleByLocale,
            'cvPublicIdentity' => $cvPublicIdentity,
            'cvPencilDecoration' => CvPencilDecorationContract::fromPayload($profilePayloadForCss),
            'siteAccentColor' => $this->siteColorsResolver->resolveAccentColor(),
            'cvEditorPlaceholderUiJson' => $cvEditorPlaceholderUiJson,
            'defaultLocale' => $defaultLocale,
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
                $latestProfile instanceof CvProfile ? (int) $latestProfile->getId() : 0
            ),
            'cvProfileId' => $latestProfile instanceof CvProfile ? (int) $latestProfile->getId() : 0,
            'cvSituationContentByLocale' => $situationContentResolved['contentByLocale'],
            'cvSkillsCatalog' => $skillsResolved['catalog'],
            'cvFlagshipProjectsSectionEnabled' => FlagshipProjectsContract::isSectionEnabledFromPayload($profilePayloadForCss),
            'cvFlagshipProjectsCanonical' => $flagshipProjectsResolved['canonicalProjects'],
            'cvFlagshipProjectsMaxCount' => FlagshipProjectsContract::MAX_PROJECTS_PER_LOCALE,
            'cvExperienceEntriesByLocale' => $experienceResolved['entriesByLocale'],
            'cvExperiencePreviewByLocale' => $this->cvExperienceSettingsService->buildAdminPreviewPayloadByLocale(
                $experienceResolved['entriesByLocale']
            ),
            'cvEducationEntriesByLocale' => $educationResolved['entriesByLocale'],
            'cvEducationPreviewByLocale' => $this->cvEducationSettingsService->buildAdminPreviewPayloadByLocale(
                $educationResolved['entriesByLocale']
            ),
            'cvCertificationEntries' => $certificationResolved['canonicalEntries'],
            'cvCertificationPreviewByLocale' => $this->cvCertificationSettingsService->buildAdminPreviewPayloadByLocale(
                $certificationResolved['canonicalEntries'],
                $activeLocales,
                $defaultLocale,
            ),
            'cvLanguagesEntries' => $languagesResolved['canonicalEntries'],
            'cvLanguagesPreviewEntries' => $languagesResolved['entries'],
            'cvInterestsEntries' => $interestsResolved['canonicalEntries'],
            'cvInterestsPreviewEntries' => $interestsResolved['entries'],
            'cvInterestsColumnsPerRow' => $interestsResolved['columnsPerRow'],
            'cvWebProfilesEntries' => $webProfilesResolved['entries'],
            'cvReferencesSectionEnabled' => $referencesResolved['sectionEnabled'],
            'cvReferencesEntriesByLocale' => $referencesResolved['entriesByLocale'],
            'cvReferencesPreviewByLocale' => $this->cvReferencesSettingsService->buildAdminPreviewPayloadByLocale(
                $referencesResolved['entriesByLocale'],
                $referencesResolved['sectionEnabled'],
            ),
            'cvAboutPreviewByLocale' => $this->cvAboutProfileSettingsService->buildAdminPreviewPayloadByLocale(
                $profileContentJson,
                $activeLocales,
                $defaultLocale,
                $patternLeftResolved['svg'],
                $patternRightResolved['svg']
            ),
            'cvSituationPreviewByLocale' => $this->cvSituationContentSettingsService->buildAdminPreviewPayloadByLocale(
                $situationContentResolved['contentByLocale'],
                $activeLocales,
                $this->decodeJsonPayload($profileContentJson),
                $experienceResolved['entriesByLocale'],
                $defaultLocale,
            ),
            'cv' => [
                'payload' => [
                    'experienceEntries' => $experienceResolved['entries'],
                    'experienceHasSecondaryVisible' => $experienceResolved['hasSecondaryVisible'],
                    'educationEntries' => $educationResolved['entries'],
                    'educationHasSecondaryVisible' => $educationResolved['hasSecondaryVisible'],
                    'certificationEntries' => $certificationResolved['entries'],
                    'certificationHasSecondaryVisible' => $certificationResolved['hasSecondaryVisible'],
                    'languageEntries' => $languagesResolved['entries'],
                    'interestEntries' => $interestsResolved['entries'],
                    'webProfileEntries' => $webProfilesResolved['entries'],
                    'referencesSectionEnabled' => $referencesResolved['sectionEnabled'],
                    'referenceEntries' => $referencesResolved['entries'],
                ],
            ],
            'cvCustomizationActiveTab' => $cvUiState->tab,
            'cvCustomizationActivePanel' => $cvUiState->panel,
            'cvCustomizationActiveLocale' => $cvUiState->locale,
            'cvCustomizationActiveEntry' => $cvUiState->entry,
            'situationBackgroundTextureCases' => SituationBackgroundTexture::casesForAdmin(),
            'experienceBackgroundTextureCases' => SituationBackgroundTexture::casesForAdmin(),
            'cvExperienceBackgroundTexture' => $experienceTexture->value,
            'cvEducationBackgroundTexture' => $educationTexture->value,
            'cvCertificationBackgroundTexture' => $certificationTexture->value,
            'cvLanguagesBackgroundTexture' => $languagesTexture->value,
            'cvInterestsBackgroundTexture' => $interestsTexture->value,
            'cvWebProfilesBackgroundTexture' => $webProfilesTexture->value,
            'cvReferencesBackgroundTexture' => $referencesTexture->value,
            'cvSectionBackgrounds' => $cvSectionBackgrounds,
            'cvPlaceholderActive' => $this->placeholderStateService->shouldUsePlaceholderMode(
                $this->decodeJsonPayload($profileContentJson)
            ),
            'profilePhotoPlaceholderPath' => CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH,
            'setupOnboarding' => $this->siteSetupOnboardingService->resolveChecklist($request->getLocale()),
        ]);
    }

    /**
     * @brief Persist page titles and `cvPublicIdentity` from the merged cv_data customization tab.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to customization index with tab and locale preserved.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function handleCvDataUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (
            !$this->isCsrfTokenValid('admin_cv_cv_data', $csrfToken)
            && !$this->isCsrfTokenValid('admin_cv_page_title', $csrfToken)
        ) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'cv_data');
        }

        $submittedPageTitles = $request->request->all('page_title');
        $pageTitleByLocale = $this->normalizePageTitleByLocale($submittedPageTitles, $activeLocales);
        if ($pageTitleByLocale === []) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.empty_page_title');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'cv_data');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        $existingContentJson = $profile instanceof CvProfile ? $profile->getContentJson() : '{}';
        $existingIdentity = $this->cvPublicIdentityAdminService->extractStoredIdentityMap($existingContentJson);
        $identityPayload = $this->cvPublicIdentityAdminService->parseFromCvDataRequest(
            $request,
            $activeLocales,
            $existingIdentity
        );
        if ($identityPayload === null) {
            $this->addFlash('warning', 'dashboard.cv_public_identity.flash.invalid');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'cv_data');
        }

        $existingBirthDate = $existingIdentity[CvPublicIdentityContract::FIELD_BIRTH_DATE] ?? null;
        $existingBirthDate = is_string($existingBirthDate) ? trim($existingBirthDate) : '';

        $defaultLocalizedTitle = $pageTitleByLocale[$defaultLocale] ?? reset($pageTitleByLocale) ?: '';
        if ($profile instanceof CvProfile) {
            $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        } else {
            $profilePayload = [];
            $profile = new CvProfile($defaultLocalizedTitle, '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload['pageTitleByLocale'] = $pageTitleByLocale;
        $profilePayload[CvPublicIdentityContract::KEY_ROOT] = $identityPayload;
        $profilePayload = CvPencilDecorationContract::mergeSubmittedFromCvDataRequest($profilePayload, $request);
        $this->persistProfilePayload($profile, $profilePayload);
        $profile->setTitle($defaultLocalizedTitle);
        $this->entityManager->flush();

        $newBirthDate = $identityPayload[CvPublicIdentityContract::FIELD_BIRTH_DATE] ?? null;
        $newBirthDate = is_string($newBirthDate) ? trim($newBirthDate) : '';
        if ($newBirthDate !== $existingBirthDate) {
            $this->employmentDocumentStorageService->purgeAllStampedPdfCaches();
        }

        $this->addFlash('success', 'dashboard.customization_cv.flash.cv_data_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'cv_data');
    }

    /**
     * @brief Handle admin update for CV professional experience entries stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleExperienceUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_experience', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'experience');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvExperienceAdminUpdateService->applyExperienceFromRequest($profilePayload, $request, $activeLocales);
        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }
        if ($result['flashError'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'experience');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.experience_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'experience');
    }

    /**
     * @brief Handle admin update for CV education entries stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleEducationUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_education', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'education');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvEducationAdminUpdateService->applyEducationFromRequest($profilePayload, $request, $activeLocales);

        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }

        if ($result['flashError'] !== [] || $result['flashWarning'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'education');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.education_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'education');
    }

    /**
     * @brief Handle admin update for CV certification entries stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to certification customization tab.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleCertificationUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_certification', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'certification');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvCertificationAdminUpdateService->applyCertificationFromRequest(
            $profilePayload,
            $request,
            $activeLocales,
            $defaultLocale,
        );

        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }

        if ($result['flashError'] !== [] || $result['flashWarning'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'certification');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.certification_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'certification');
    }

    /**
     * @brief Handle admin update for CV language entries stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to languages customization tab.
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function handleLanguagesUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_languages', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'languages');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvLanguagesAdminUpdateService->applyLanguagesFromRequest(
            $profilePayload,
            $request,
            $activeLocales,
            $defaultLocale,
        );

        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }

        if ($result['flashError'] !== [] || $result['flashWarning'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'languages');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.languages_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'languages');
    }

    /**
     * @brief Handle admin update for CV interest entries stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to interests customization tab.
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function handleInterestsUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_interests', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'interests');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvInterestsAdminUpdateService->applyInterestsFromRequest(
            $profilePayload,
            $request,
            $activeLocales,
            $defaultLocale,
        );

        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }

        if ($result['flashError'] !== [] || $result['flashWarning'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'interests');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.interests_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'interests');
    }

    /**
     * @brief Handle admin update for interests grid density stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to interests customization tab.
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function handleInterestsLayoutUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_interests_layout', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'interests');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $profilePayload[InterestsContract::KEY_COLUMNS_PER_ROW] = InterestsContract::normalizeColumnsPerRow(
            $request->request->get('interests_columns_per_row')
        );

        $this->persistProfilePayload($profile, $profilePayload);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.interests_layout_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'interests');
    }

    /**
     * @brief Handle admin update for CV web profile links stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to web profiles customization tab.
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function handleWebProfilesUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_web_profiles', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'web_profiles');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvWebProfilesAdminUpdateService->applyWebProfilesFromRequest($profilePayload, $request);

        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }

        if ($result['flashError'] !== [] || $result['flashWarning'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'web_profiles');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.web_profiles_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'web_profiles');
    }

    /**
     * @brief Handle admin update for CV reference entries stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to references customization tab.
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function handleReferencesUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_references', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'references');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvReferencesAdminUpdateService->applyReferencesFromRequest($profilePayload, $request, $activeLocales);

        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }

        if ($result['flashError'] !== [] || $result['flashWarning'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'references');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flash.references_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'references');
    }

    /**
     * @brief Handle admin update for CV Situation editorial content stored in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response
     * @date 2026-05-20
     * @author Stephane H.
     */
    private function handleSituationContentUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_situation_content', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.situation_content.flash_invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'about');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvSituationContentAdminUpdateService->applySituationContentFromRequest(
            $profilePayload,
            $request,
            $activeLocales,
        );
        if ($result['flashError'] !== []) {
            foreach ($result['flashError'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'about');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.situation_content.flash_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'about');
    }

    /**
     * @brief Persist flagship projects section visibility and project cards for the public CV.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @return Response Redirect to customization index.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function handleFlagshipProjectsUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_flagship_projects', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flagship_projects.flash_invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'flagship_projects');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $result = $this->cvFlagshipProjectsAdminUpdateService->applyFlagshipProjectsFromRequest(
            $profilePayload,
            $request,
            $activeLocales,
            $defaultLocale,
        );

        if ($result['flashStructuredWarning'] !== []) {
            $this->flashStructuredValidationErrors($result['flashStructuredWarning']);

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'flagship_projects');
        }

        foreach ($result['flashWarning'] as $messageKey) {
            $this->addFlash('warning', $messageKey);
        }

        if ($result['flashWarning'] !== []) {
            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'flagship_projects');
        }

        $this->persistProfilePayload($profile, $result['payload']);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.flagship_projects.flash_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'flagship_projects');
    }

    /**
     * @brief Resolve eligible section slug from a form_scope suffix (for example `_background`).
     *
     * @param string $formScope Posted form scope value.
     * @param string $suffix Expected suffix such as `_background`.
     * @return string|null Section key or null when not eligible (situation background is not admin-customizable).
     * @date 2026-05-29
     * @author Stephane H.
     */
    private function resolveSectionKeyFromFormScope(string $formScope, string $suffix): ?string
    {
        if (!str_ends_with($formScope, $suffix)) {
            return null;
        }

        $sectionKey = substr($formScope, 0, -strlen($suffix));
        if (!in_array($sectionKey, SectionTransitionContract::ELIGIBLE_SECTION_KEYS, true)) {
            return null;
        }

        if ($sectionKey === 'situation') {
            return null;
        }

        return $sectionKey;
    }

    /**
     * @brief Handle admin update for one section background block in content_json.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $sectionKey Eligible section slug (situation excluded).
     * @return Response Redirect to customization index.
     * @date 2026-05-29
     * @author Stephane H.
     */
    private function handleSectionBackgroundUpdate(
        Request $request,
        array $activeLocales,
        string $defaultLocale,
        string $sectionKey,
    ): Response {
        $csrfTokenId = 'admin_cv_'.$sectionKey.'_background';
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid($csrfTokenId, $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.section_background.flash_invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, $sectionKey);
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());
        $submittedBlocks = $request->request->all('section_backgrounds');
        $sectionSubmitted = is_array($submittedBlocks[$sectionKey] ?? null) ? $submittedBlocks[$sectionKey] : [];

        $legacyTextureField = match ($sectionKey) {
            'experience' => 'experience_background_texture',
            default => null,
        };
        if ($legacyTextureField !== null && $request->request->has($legacyTextureField)) {
            $sectionSubmitted['texture'] = $request->request->get($legacyTextureField);
        }

        $profilePayload = SectionBackgroundContract::mergeSubmittedSectionIntoPayload(
            $profilePayload,
            $sectionKey,
            $sectionSubmitted
        );
        $this->persistProfilePayload($profile, $profilePayload);
        $this->entityManager->flush();

        $this->addFlash('success', 'dashboard.customization_cv.section_background.flash_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, $sectionKey);
    }

    /**
     * @brief Handle admin update for CV About profile photo upload and localized presentation HTML.
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locales for localized About presentation bodies.
     * @param string $defaultLocale Site default locale for legacy migration alignment.
     * @return Response
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function handleAboutImagesUpdate(Request $request, array $activeLocales, string $defaultLocale): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('admin_cv_about_images', $csrfToken)) {
            $this->addFlash('warning', 'dashboard.customization_cv.flash.invalid_csrf');

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'about');
        }

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if (!$profile instanceof CvProfile) {
            $profile = new CvProfile('default', '{}');
            $this->entityManager->persist($profile);
        }

        $profilePayload = $this->decodeJsonPayload($profile->getContentJson());

        try {
            $updateResult = $this->cvAboutAdminUpdateService->applyAboutImagesRequest(
                $profilePayload,
                $request,
                $activeLocales
            );
            $profilePayload = $updateResult['payload'];
            foreach ($updateResult['flashSuccess'] as $messageKey) {
                $this->addFlash('success', $messageKey);
            }
            foreach ($updateResult['flashWarning'] as $messageKey) {
                $this->addFlash('warning', $messageKey);
            }
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'about');
        }

        $this->persistProfilePayload($profile, $profilePayload);
        $this->entityManager->flush();
        $this->addFlash('success', 'dashboard.customization_cv.flash.about_visual_saved');

        return $this->redirectToCvCustomizationIndexFromRequest($request, $activeLocales, $defaultLocale, 'about');
    }

    /**
     * @brief Push one flash toast per structured validation error payload.
     *
     * @param list<array{message: string, parameters?: array<string, string>}> $errors Validation error payloads.
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function flashStructuredValidationErrors(array $errors): void
    {
        foreach ($errors as $error) {
            if (!is_array($error) || !isset($error['message']) || !is_string($error['message'])) {
                continue;
            }

            $parameters = $error['parameters'] ?? [];
            if (!is_array($parameters)) {
                $parameters = [];
            }

            /** @var array<string, string> $normalizedParameters */
            $normalizedParameters = [];
            foreach ($parameters as $key => $value) {
                if (is_string($key) && (is_string($value) || is_numeric($value))) {
                    $normalizedParameters[$key] = (string) $value;
                }
            }

            $this->addFlash('warning', [
                'message' => $error['message'],
                'parameters' => $normalizedParameters,
            ]);
        }
    }

    /**
     * @brief Redirect to CV customization index preserving tab, panel, and locale from the request.
     *
     * @param Request $request HTTP request with query or hidden UI state fields.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $tabSlug Logical tab to enforce when not present in the request.
     * @return Response Redirect response with validated query parameters.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function redirectToCvCustomizationIndexFromRequest(
        Request $request,
        array $activeLocales,
        string $defaultLocale,
        string $tabSlug,
    ): Response {
        $state = $this->customizationUiStateResolver->resolveCvFromRequest($request, $activeLocales, $defaultLocale);
        $forced = $this->customizationUiStateResolver->resolveCvState(
            $tabSlug,
            $state->panel,
            $state->locale,
            $activeLocales,
            $defaultLocale,
            $state->entry,
        );

        return $this->redirectToRoute('admin_cv_index', $this->customizationUiStateResolver->buildCvRedirectParams($forced));
    }

    /**
     * @brief Parse About presentation HTML per locale (empty editor restores default skeleton).
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Allowed locale codes for submission whitelist.
     * @return array<string, array<string, string>> Map with {@see AboutPresentationContract::KEY_HTML_BY_LOCALE}.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function parseAboutPresentationUpdate(Request $request, array $activeLocales): array
    {
        $submitted = $request->request->all('about_presentation_html');
        if (!is_array($submitted)) {
            $submitted = [];
        }

        $htmlByLocale = [];
        foreach ($activeLocales as $locale) {
            $raw = $submitted[$locale] ?? '';
            $rawStr = is_string($raw) ? $raw : '';
            $htmlByLocale[$locale] = $this->cvAboutProfileSettingsService->normalizePresentationHtmlForStorage(
                $rawStr,
                $locale
            );
        }

        return [
            AboutPresentationContract::KEY_HTML_BY_LOCALE => $htmlByLocale,
        ];
    }

    /**
     * @brief Normalize stored profile photo path for custom file deletion checks.
     * @param mixed $rawPath Raw payload path.
     * @return string Resolved display path for admin preview (placeholder when no custom upload).
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function normalizeProfilePhotoStoredPath(mixed $rawPath): string
    {
        return $this->cvAboutProfileSettingsService->resolveProfilePhotoDisplayPath($rawPath);
    }

    /**
     * @brief Persist sanitized JSON when legacy keys are still stored in the database row.
     *
     * @param CvProfile $profile Profile entity loaded for the admin customization index.
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function migrateProfilePayloadIfNeeded(CvProfile $profile): void
    {
        $raw = $this->decodeJsonPayload($profile->getContentJson());
        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($raw);

        $rawJson = json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sanitizedJson = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($rawJson === $sanitizedJson) {
            return;
        }

        $this->persistProfilePayload($profile, $sanitized);
        $this->entityManager->flush();
    }

    /**
     * @brief Encode sanitized profile JSON and assign it to the CvProfile entity.
     *
     * @param CvProfile $profile Target profile row.
     * @param array<string, mixed> $profilePayload Decoded content JSON before sanitization.
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function persistProfilePayload(CvProfile $profile, array $profilePayload): void
    {
        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($profilePayload);
        $profile->setContentJson((string) json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @brief Decode JSON payload into associative array.
     *
     * @param string $json JSON payload text.
     * @return array<string, mixed>
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function decodeJsonPayload(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @brief Keep only non-empty localized page titles for allowed locales.
     * @param array<string, mixed> $submittedPageTitles Raw submitted page titles by locale.
     * @param array<int, string> $activeLocales Allowed active locales.
     * @return array<string, string>
     * @date 2026-05-08
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
     * @param string $contentJson Profile JSON payload.
     * @param array<int, string> $activeLocales Allowed active locales.
     * @return array<string, string>
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function extractPageTitleByLocale(string $contentJson, array $activeLocales): array
    {
        $payload = $this->decodeJsonPayload($contentJson);
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
     * @brief Normalize checkbox-like request values to bool.
     *
     * @param mixed $value Raw request value.
     * @return bool
     * @date 2026-05-15
     * @author Stephane H.
     */
    private static function normalizeBoolFromRequest(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * @brief Store uploaded About section SVG pattern after strict palette validation.
     *
     * @param UploadedFile $uploadedFile Uploaded SVG file.
     * @param mixed $displayNameSubmitted Raw display name input.
     * @param mixed $sideSubmitted Raw side input (`left` or `right`).
     * @return array{templateId: string, warnings: list<string>} Stored template id (warnings always empty).
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function storeAboutPatternTemplateUpload(
        UploadedFile $uploadedFile,
        mixed $displayNameSubmitted = null,
        mixed $sideSubmitted = null,
    ): array
    {
        $mimeType = strtolower((string) $uploadedFile->getMimeType());
        $allowedMimeTypes = ['image/svg+xml', 'text/plain', 'application/xml', 'text/xml'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.about_section_customization.pattern_upload_invalid_type');
        }

        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));
        if ($extension !== 'svg') {
            throw new \InvalidArgumentException('dashboard.customization_cv.about_section_customization.pattern_upload_invalid_type');
        }

        $svg = file_get_contents($uploadedFile->getPathname());
        if (!is_string($svg) || trim($svg) === '') {
            throw new \InvalidArgumentException('dashboard.customization_cv.about_section_customization.pattern_upload_invalid_type');
        }

        $displayName = is_string($displayNameSubmitted) ? trim($displayNameSubmitted) : '';
        if ($displayName === '') {
            $originalFilename = pathinfo((string) $uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $displayName = is_string($originalFilename) ? trim($originalFilename) : '';
        }
        if ($displayName === '') {
            $displayName = null;
        }
        $side = is_string($sideSubmitted) ? $sideSubmitted : null;
        $templateId = $this->cvAboutPatternTemplateService->storeUploadedTemplate($svg, $displayName, $side);

        return [
            'templateId' => $templateId,
            'warnings' => [],
        ];
    }

    /**
     * @brief Persist uploaded about image in custom directory with strict mime checks.
     * @param UploadedFile $uploadedFile Uploaded image.
     * @param string $suffixName Target suffix for generated filename.
     * @return string
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function storeAboutImageUpload(UploadedFile $uploadedFile, string $suffixName): string
    {
        $allowedMimeTypes = ['image/webp', 'image/png', 'image/jpeg'];
        $mimeType = (string) $uploadedFile->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.invalid_image');
        }

        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));
        if (!in_array($extension, ['webp', 'png', 'jpg', 'jpeg'], true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.invalid_image');
        }

        $targetRelativeDirectory = CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT;
        $targetDirectory = rtrim((string) $this->getParameter('kernel.project_dir'), '/').'/public/'.$targetRelativeDirectory;
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $targetFilename = sprintf('about-%s-%s.%s', $suffixName, bin2hex(random_bytes(8)), $extension);
        $absoluteTargetPath = $targetDirectory.'/'.$targetFilename;
        $this->imageReencoder->reencodeToPath($uploadedFile, $absoluteTargetPath, $mimeType);

        return $targetRelativeDirectory.'/'.$targetFilename;
    }

    /**
     * @brief Delete previous custom about image file when replaced.
     * @param string $relativePath Existing relative path.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function deleteCustomAboutImageIfNeeded(string $relativePath): void
    {
        if (!str_starts_with($relativePath, CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT.'/')) {
            return;
        }

        $absolutePath = rtrim((string) $this->getParameter('kernel.project_dir'), '/').'/public/'.$relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * @brief Build value/unit rows for About presentation typography admin inputs.
     *
     * @param array<string, string> $typography Normalized font sizes per element key.
     * @return array<string, array{value: string, unit: string}>
     * @date 2026-05-23
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
