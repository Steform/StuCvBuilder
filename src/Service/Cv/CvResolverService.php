<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Service\Cv\EducationContract;
use App\Service\Cv\ExperienceContract;
use App\Cv\CvPencilDecorationContract;
use App\Cv\SectionBackgroundContract;
use App\Cv\SectionTransitionContract;
use App\Cv\SituationBackgroundTexture;
use App\Repository\CvProfileRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Employment\CompanyCvAboutCustomizationService;
use App\Service\Employment\CompanyCvCertificationCustomizationService;
use App\Service\Employment\CompanyCvEducationCustomizationService;
use App\Service\Employment\CompanyCvInterestsCustomizationService;
use App\Service\Employment\CompanyCvLanguagesCustomizationService;
use App\Service\Employment\CompanyCvReferencesCustomizationService;
use App\Service\Employment\CompanyCvWebProfilesCustomizationService;
use App\Service\Employment\CompanyCvExperienceCustomizationService;
use App\Service\Employment\CompanyCvFlagshipProjectsCustomizationService;
use App\Service\Employment\CompanyCvSkillsCustomizationService;
use App\Service\Employment\CompanyCvSituationCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Site\SiteColorsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service CvResolverService.
 */
class CvResolverService
{
    public function __construct(
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CvPublicIdentityPlaceholderService $cvPublicIdentityPlaceholderService,
        private readonly CvExperienceSettingsService $cvExperienceSettingsService,
        private readonly CvEducationSettingsService $cvEducationSettingsService,
        private readonly CvCertificationSettingsService $cvCertificationSettingsService,
        private readonly CvSkillsSettingsService $cvSkillsSettingsService,
        private readonly CvFlagshipProjectsSettingsService $cvFlagshipProjectsSettingsService,
        private readonly CvSituationContentSettingsService $cvSituationContentSettingsService,
        private readonly CvAboutProfileSettingsService $cvAboutProfileSettingsService,
        private readonly CvAboutPatternTemplateService $cvAboutPatternTemplateService,
        private readonly CvPencilDecorationService $cvPencilDecorationService,
        private readonly AboutPresentationDefaultContentService $aboutPresentationDefaultContent,
        private readonly CustomizationPlaceholderStateService $placeholderStateService,
        private readonly SiteColorsResolver $siteColorsResolver,
        private readonly TranslatorInterface $translator,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly CompanyCvAboutCustomizationService $companyCvAboutCustomizationService,
        private readonly CompanyCvSituationCustomizationService $companyCvSituationCustomizationService,
        private readonly CompanyCvExperienceCustomizationService $companyCvExperienceCustomizationService,
        private readonly CompanyCvSkillsCustomizationService $companyCvSkillsCustomizationService,
        private readonly CompanyCvFlagshipProjectsCustomizationService $companyCvFlagshipProjectsCustomizationService,
        private readonly CompanyCvEducationCustomizationService $companyCvEducationCustomizationService,
        private readonly CompanyCvCertificationCustomizationService $companyCvCertificationCustomizationService,
        private readonly CvLanguagesSettingsService $cvLanguagesSettingsService,
        private readonly CvInterestsSettingsService $cvInterestsSettingsService,
        private readonly CvWebProfilesSettingsService $cvWebProfilesSettingsService,
        private readonly CvReferencesSettingsService $cvReferencesSettingsService,
        private readonly CompanyCvLanguagesCustomizationService $companyCvLanguagesCustomizationService,
        private readonly CompanyCvInterestsCustomizationService $companyCvInterestsCustomizationService,
        private readonly CompanyCvWebProfilesCustomizationService $companyCvWebProfilesCustomizationService,
        private readonly CompanyCvReferencesCustomizationService $companyCvReferencesCustomizationService,
        private readonly CvPublicNavVisibilityService $cvPublicNavVisibilityService,
    ) {
    }

    /**
     * @brief Resolve default CV payload; echoes format query for tracking and future targeted overrides.
     *
     * @param string $formatCode Format code from the request query (reserved for future use).
     * @param string|null $displayLocale Request locale for projected {@see AboutPresentationContract::KEY_HTML}; null uses configured default.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolve(string $formatCode, ?string $displayLocale = null): array
    {
        $defaultProfile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if ($defaultProfile === null) {
            return [
                'view' => 'default',
                'formatCode' => $formatCode,
                'companyCode' => 'default',
                'companyResolved' => false,
                'title' => '',
                'isPlaceholderMode' => false,
                'cvProfileId' => 0,
                'aboutProfileCssCacheSuffix' => '0',
                'aboutPatternCssCacheSuffix' => $this->siteColorsResolver->patternCssCacheSuffix([]),
                'siteColorsLayoutCssCacheSuffix' => $this->siteColorsResolver->layoutCssCacheSuffix(),
                'payload' => [
                    'publicNavVisibility' => $this->cvPublicNavVisibilityService->resolve([], []),
                ],
                'targetFound' => false,
            ];
        }

        $defaultPayload = $this->decodeJson($defaultProfile->getContentJson());
        $isPlaceholderMode = $this->placeholderStateService->shouldUsePlaceholderMode($defaultPayload);
        $display = $displayLocale ?? 'fr';
        $resolvedTitle = $isPlaceholderMode
            ? $this->translator->trans('cv.placeholder.page_title', [], 'messages', $display)
            : ($defaultProfile->getTitle() ?? '');

        $profileId = (int) $defaultProfile->getId();
        $defaultPayload[CvPublicIdentityContract::KEY_EMPLOYMENT_FORMAT_CODE] = $formatCode;

        $company = $formatCode !== ''
            ? $this->trackedCompanyRepository->findActiveByCode($formatCode)
            : null;
        $companyCode = $company !== null ? $company->getCode() : 'default';
        $companyResolved = $company !== null;

        $localeConfig = $this->localeConfigurationService->getConfiguration();
        /** @var list<string> $activeLocales */
        $activeLocales = is_array($localeConfig['activeLocales'] ?? null) ? $localeConfig['activeLocales'] : ['fr'];
        $defaultLocale = is_string($localeConfig['defaultLocale'] ?? null) ? $localeConfig['defaultLocale'] : ($activeLocales[0] ?? 'fr');

        $defaultPayload = $this->companyCvAboutCustomizationService->mergeAboutOverrideIntoPayload($defaultPayload, $company);
        $defaultPayload = $this->companyCvSituationCustomizationService->mergeSituationOverrideIntoPayload($defaultPayload, $company);
        $defaultPayload = $this->companyCvExperienceCustomizationService->mergeExperienceOverrideIntoPayload($defaultPayload, $company);
        $defaultPayload = $this->companyCvSkillsCustomizationService->mergeSkillsOverrideIntoPayload(
            $defaultPayload,
            $company,
            $activeLocales,
            $defaultLocale,
        );
        $defaultPayload = $this->companyCvFlagshipProjectsCustomizationService->mergeFlagshipProjectsOverrideIntoPayload(
            $defaultPayload,
            $company,
        );
        $defaultPayload = $this->companyCvEducationCustomizationService->mergeEducationOverrideIntoPayload(
            $defaultPayload,
            $company,
        );
        $defaultPayload = $this->companyCvCertificationCustomizationService->mergeCertificationOverrideIntoPayload(
            $defaultPayload,
            $company,
        );
        $defaultPayload = $this->companyCvLanguagesCustomizationService->mergeLanguagesOverrideIntoPayload(
            $defaultPayload,
            $company,
        );
        $defaultPayload = $this->companyCvInterestsCustomizationService->mergeInterestsOverrideIntoPayload(
            $defaultPayload,
            $company,
        );
        $defaultPayload = $this->companyCvWebProfilesCustomizationService->mergeWebProfilesOverrideIntoPayload(
            $defaultPayload,
            $company,
        );
        $defaultPayload = $this->companyCvReferencesCustomizationService->mergeReferencesOverrideIntoPayload(
            $defaultPayload,
            $company,
        );
        $payload = $this->sanitizeAboutPresentationHtmlInPayload($defaultPayload, $displayLocale, $isPlaceholderMode);
        $payload['publicNavVisibility'] = $this->cvPublicNavVisibilityService->resolve($defaultPayload, $payload);

        $aboutCssCacheKey = $profileId;
        if ($company !== null && $this->companyCvAboutCustomizationService->isAboutCustomized($company)) {
            $aboutCssCacheKey = ($profileId * 100_000) + (int) $company->getId();
        }

        $result = [
            'view' => 'default',
            'formatCode' => $formatCode,
            'companyCode' => $companyCode,
            'companyResolved' => $companyResolved,
            'title' => $resolvedTitle,
            'isPlaceholderMode' => $isPlaceholderMode,
            'cvProfileId' => $profileId,
            'aboutProfileCssCacheSuffix' => AboutPresentationContract::stylesheetCacheSuffixFromPayload($payload, $aboutCssCacheKey),
            'aboutPatternCssCacheSuffix' => $this->siteColorsResolver->patternCssCacheSuffix($payload),
            'siteColorsLayoutCssCacheSuffix' => $this->siteColorsResolver->layoutCssCacheSuffix(),
            'payload' => $payload,
            'targetFound' => $companyResolved,
        ];

        if ($formatCode !== '') {
            $result['requestedFormatCode'] = $formatCode;
        }

        return $result;
    }

    /**
     * @brief Sanitize About presentation HTML map, apply default skeleton when empty, then `[[cv.*]]` substitution.
     *
     * @param array<string, mixed> $payload Resolved CV payload array.
     * @param string|null $displayLocale Preferred locale for projected HTML.
     * @param bool $isPlaceholderMode When true, all locales use the default presentation skeleton.
     * @return array<string, mixed> Payload with `aboutPresentationHtml` and `aboutPresentationHtmlByLocale` set.
     * @date 2026-05-28
     * @author Stephane H.
     */
    private function sanitizeAboutPresentationHtmlInPayload(array $payload, ?string $displayLocale, bool $isPlaceholderMode = false): array
    {
        $config = $this->localeConfigurationService->getConfiguration();
        /** @var list<string> $activeLocales */
        $activeLocales = $config['activeLocales'] ?? ['fr'];
        $defaultLocale = is_string($config['defaultLocale'] ?? null) ? $config['defaultLocale'] : ($activeLocales[0] ?? 'fr');
        $display = ($displayLocale !== null && $displayLocale !== '') ? $displayLocale : $defaultLocale;

        $sanitizedMap = [];
        $rawByLocale = AboutPresentationContract::htmlByLocaleFromStoredPayload($payload, $activeLocales, $defaultLocale);
        foreach ($activeLocales as $loc) {
            $raw = $rawByLocale[$loc] ?? '';
            $rawStr = is_string($raw) ? $raw : '';
            $sanitized = $this->richHtmlSanitizer->sanitize($rawStr);
            $isEmpty = $this->richHtmlSanitizer->isEffectivelyEmpty($sanitized);
            if ($isPlaceholderMode || $isEmpty) {
                $sanitizedMap[$loc] = $this->richHtmlSanitizer->capitalizePresentationHeadingFirstLetters(
                    $this->aboutPresentationDefaultContent->buildSanitizedHtmlForLocale($loc)
                );

                continue;
            }

            $sanitizedMap[$loc] = $this->richHtmlSanitizer->capitalizePresentationHeadingFirstLetters($sanitized);
        }

        $now = new \DateTimeImmutable('now');
        $afterPlaceholders = $this->cvPublicIdentityPlaceholderService->applyToSanitizedPresentation(
            $sanitizedMap,
            $payload,
            $display,
            $activeLocales,
            $defaultLocale,
            $now
        );
        $payload[AboutPresentationContract::KEY_HTML_BY_LOCALE] = $afterPlaceholders['htmlByLocale'];
        $payload[AboutPresentationContract::KEY_HTML] = $afterPlaceholders['html'];

        $payload['aboutProfilePhotoHasUserUpload'] = $this->cvAboutProfileSettingsService->hasUserProfilePhoto(
            $payload['aboutProfilePhotoPath'] ?? null
        );
        $payload['aboutProfilePhotoDisplayPath'] = $this->cvAboutProfileSettingsService->resolveProfilePhotoDisplayPath(
            $payload['aboutProfilePhotoPath'] ?? null
        );
        $aboutPattern = AboutSectionPatternCustomizationContract::fromPayload($payload);
        $payload['aboutPatternLeftSvgMarkup'] = $this->cvAboutPatternTemplateService
            ->renderTemplate($aboutPattern['patternLeftId'] ?? null)['svg'];
        $payload['aboutPatternRightSvgMarkup'] = $this->cvAboutPatternTemplateService
            ->renderTemplate($aboutPattern['patternRightId'] ?? null)['svg'];
        $payload['cvPencilDecorationEnabled'] = CvPencilDecorationContract::isEnabledFromPayload($payload);
        $payload['cvPencilSvgMarkup'] = $this->cvPencilDecorationService->renderSvgMarkup();
        $payload['aboutHeaderLocationLine'] = $this->cvPublicIdentityPlaceholderService->resolveAboutHeaderLocationLine(
            $payload,
            $display,
            $activeLocales,
            $defaultLocale
        );
        $payload = SectionBackgroundContract::applyNormalizedMapToPayload($payload);
        $payload[SectionBackgroundContract::KEY] = SectionBackgroundContract::normalizeMap(
            $payload[SectionBackgroundContract::KEY] ?? null,
            $payload
        );
        $payload['situationBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'situation');
        $payload['experienceBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'experience');
        $payload['educationBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'education');
        $payload['certificationBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'certification');
        $payload['languagesBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'languages');
        $payload['interestsBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'interests');
        $payload['webProfilesBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'web_profiles');
        $payload['referencesBackgroundTexture'] = SectionBackgroundContract::resolveTextureForSection($payload, 'references');
        $payload[SectionTransitionContract::KEY] = SectionTransitionContract::normalizeMap(
            $payload[SectionTransitionContract::KEY] ?? null
        );

        $payload = $this->mergeExperienceIntoPayload($payload, $display, $activeLocales, $defaultLocale);
        $payload = $this->mergeEducationIntoPayload($payload, $display, $activeLocales, $defaultLocale);
        $payload = $this->mergeCertificationIntoPayload($payload, $display, $activeLocales, $defaultLocale);
        $payload = $this->mergeSkillsIntoPayload($payload, $display, $activeLocales, $defaultLocale);
        $payload = $this->mergeFlagshipProjectsIntoPayload($payload, $display, $activeLocales, $defaultLocale);
        $payload = $this->mergeLanguagesIntoPayload($payload, $display, $activeLocales, $defaultLocale);
        $payload = $this->mergeInterestsIntoPayload($payload, $display, $activeLocales, $defaultLocale);
        $payload = $this->mergeWebProfilesIntoPayload($payload, $display);
        $payload = $this->mergeReferencesIntoPayload($payload, $display, $activeLocales, $defaultLocale);

        $payload[FlagshipProjectsContract::KEY_SECTION_ENABLED] = FlagshipProjectsContract::isSectionEnabledFromPayload($payload);
        $payload[ReferencesContract::KEY_SECTION_ENABLED] = ReferencesContract::isSectionEnabledFromPayload($payload);

        $payload = $this->mergeSituationContentIntoPayload($payload, $display, $activeLocales, $defaultLocale);

        return $payload;
    }

    /**
     * @brief Attach resolved experience entries and secondary visibility flag for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-05-20
     * @author Stephane H.
     */
    private function mergeExperienceIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale
    ): array {
        $resolved = $this->cvExperienceSettingsService->resolveFromPayload(
            $payload,
            $activeLocales,
            $defaultLocale,
            $displayLocale
        );

        $payload['experienceEntries'] = $this->cvExperienceSettingsService->filterPrimaryVisible($resolved['entries']);
        $payload['experienceEntriesFull'] = $resolved['entriesFull'];
        $payload['experienceHasSecondaryVisible'] = $resolved['hasSecondaryVisible'];

        return $payload;
    }

    /**
     * @brief Attach resolved education entries and secondary visibility flag for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function mergeEducationIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale
    ): array {
        $resolved = $this->cvEducationSettingsService->resolveFromPayload(
            $payload,
            $activeLocales,
            $defaultLocale,
            $displayLocale
        );

        $payload['educationEntries'] = $this->cvEducationSettingsService->filterPrimaryVisible($resolved['entries']);
        $payload['educationEntriesFull'] = $resolved['entriesFull'];
        $payload['educationHasSecondaryVisible'] = $resolved['hasSecondaryVisible'];

        return $payload;
    }

    /**
     * @brief Attach resolved certification entries and secondary visibility flag for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function mergeCertificationIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale
    ): array {
        $contentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resolved = $this->cvCertificationSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale
        );

        $payload['certificationEntries'] = $resolved['entries'];
        $payload['certificationEntriesFull'] = $resolved['entriesFull'];
        $payload['certificationHasSecondaryVisible'] = $resolved['hasSecondaryVisible'];

        return $payload;
    }

    /**
     * @brief Attach resolved skills trees, full catalog view, and secondary visibility for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function mergeSkillsIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale
    ): array {
        $resolved = $this->cvSkillsSettingsService->resolveFromPayload(
            $payload,
            $activeLocales,
            $defaultLocale,
            $displayLocale
        );

        $payload['skillsTreePrimary'] = $resolved['treePrimary'];
        $payload['skillsTreeFull'] = $resolved['treeFull'];
        $payload['skillsHasSecondaryVisible'] = $resolved['hasSecondaryVisible'];

        return $payload;
    }

    /**
     * @brief Attach resolved language entries for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function mergeLanguagesIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $contentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resolved = $this->cvLanguagesSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale,
        );
        $payload['languageEntries'] = $resolved['entries'];

        return $payload;
    }

    /**
     * @brief Attach resolved interest entries for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function mergeInterestsIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $contentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resolved = $this->cvInterestsSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale,
        );
        $payload['interestEntries'] = $resolved['entries'];
        $payload[InterestsContract::KEY_COLUMNS_PER_ROW] = $resolved['columnsPerRow'];

        return $payload;
    }

    /**
     * @brief Attach resolved web profile links for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function mergeWebProfilesIntoPayload(array $payload, string $displayLocale): array
    {
        $contentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resolved = $this->cvWebProfilesSettingsService->resolveFromContentJson($contentJson, $displayLocale);
        $payload['webProfileEntries'] = $resolved['entries'];

        return $payload;
    }

    /**
     * @brief Attach resolved reference entries for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function mergeReferencesIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $contentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resolved = $this->cvReferencesSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale,
        );
        $payload['referenceEntries'] = $resolved['entries'];

        return $payload;
    }

    /**
     * @brief Attach resolved flagship projects for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function mergeFlagshipProjectsIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale
    ): array {
        $contentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resolved = $this->cvFlagshipProjectsSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale
        );

        $payload['flagshipProjects'] = $resolved['projects'];
        $payload['flagshipProjectsFull'] = $resolved['projectsFull'];
        $payload['flagshipProjectsHasSecondaryVisible'] = $resolved['hasSecondaryVisible'];

        return $payload;
    }

    /**
     * @brief Attach resolved situation editorial content for public templates.
     *
     * @param array<string, mixed> $payload Merged CV payload.
     * @param string $displayLocale Viewer locale.
     * @param list<string> $activeLocales Active locales.
     * @param string $defaultLocale Default locale.
     * @return array<string, mixed>
     * @date 2026-05-20
     * @author Stephane H.
     */
    private function mergeSituationContentIntoPayload(
        array $payload,
        string $displayLocale,
        array $activeLocales,
        string $defaultLocale
    ): array {
        $contentJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resolved = $this->cvSituationContentSettingsService->resolveFromContentJson(
            $contentJson,
            $activeLocales,
            $defaultLocale,
            $displayLocale
        );

        $payload['situationContent'] = $resolved['content'];
        $payload['situationLastExperienceEntry'] = $this->resolveFirstPrimaryExperienceEntry(
            $payload['experienceEntries'] ?? null
        );

        return $payload;
    }

    /**
     * @brief Pick the first primary experience row for the Situation footnote (timeline order).
     *
     * @param mixed $entries Resolved `experienceEntries` list from {@see mergeExperienceIntoPayload()}.
     * @return array<string, mixed>|null Entry map or null when none.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveFirstPrimaryExperienceEntry(mixed $entries): ?array
    {
        if (!is_array($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['isPrimary'] ?? true) === true) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @brief Decode JSON payload as associative array.
     *
     * @param string $json JSON payload.
     * @return array<string, mixed>
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function decodeJson(string $json): array
    {
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : [];
    }
}
