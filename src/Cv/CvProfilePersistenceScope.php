<?php

declare(strict_types=1);

namespace App\Cv;

use App\Service\Cv\AboutPresentationContract;
use App\Service\Cv\CvPublicIdentityContract;
use App\Cv\CvPencilDecorationContract;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\EducationContract;
use App\Service\Cv\ExperienceContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\InterestsContract;
use App\Service\Cv\LanguagesContract;
use App\Service\Cv\ReferencesContract;
use App\Service\Cv\SituationContentContract;
use App\Service\Cv\WebProfilesContract;

/**
 * @brief Canonical whitelist and legacy purge rules for CvProfile `contentJson` persistence and customization backup.
 *
 * @date 2026-05-27
 * @author Stephane H.
 */
final class CvProfilePersistenceScope
{
    /**
     * @var list<string> Top-level JSON keys allowed in the database and customization backup archives.
     */
    public const PERSISTED_TOP_LEVEL_KEYS = [
        'pageTitleByLocale',
        CvPublicIdentityContract::KEY_ROOT,
        ExperienceContract::KEY_ENTRIES_BY_LOCALE,
        EducationContract::KEY_ENTRIES_BY_LOCALE,
        CertificationContract::KEY_ENTRIES,
        SkillsTreeContract::KEY,
        SituationContentContract::KEY_CONTENT_BY_LOCALE,
        FlagshipProjectsContract::KEY_SECTION_ENABLED,
        FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE,
        LanguagesContract::KEY_ENTRIES,
        InterestsContract::KEY_ENTRIES,
        InterestsContract::KEY_COLUMNS_PER_ROW,
        WebProfilesContract::KEY_ENTRIES,
        ReferencesContract::KEY_SECTION_ENABLED,
        ReferencesContract::KEY_ENTRIES_BY_LOCALE,
        'aboutProfilePhotoPath',
        AboutPresentationContract::KEY_HTML_BY_LOCALE,
        AboutPresentationTypographyContract::KEY,
        AboutSectionPatternCustomizationContract::KEY,
        CvPencilDecorationContract::KEY,
        SectionBackgroundContract::KEY,
        'situationBackgroundTexture',
        'experienceBackgroundTexture',
        'educationBackgroundTexture',
        'certificationBackgroundTexture',
        'languagesBackgroundTexture',
        'interestsBackgroundTexture',
        'webProfilesBackgroundTexture',
        'referencesBackgroundTexture',
    ];

    /**
     * @var list<string> Obsolete top-level keys removed on every persist, export, and restore.
     */
    public const OBSOLETE_TOP_LEVEL_KEYS = [
        SectionTransitionContract::KEY,
        AboutSectionPatternCustomizationContract::LEGACY_COLOR_KEY,
        AboutPresentationContract::KEY_HTML,
        AboutPresentationContract::KEY_LAYOUT_DESKTOP,
        AboutPresentationContract::KEY_LAYOUT_MOBILE,
        'aboutSectionThemeColors',
        'aboutSectionAtmosphereStyle',
        'aboutSectionAtmosphereCssSanitized',
        'aboutBackgroundPrimary',
        'aboutBackgroundSecondary',
        'aboutHaloStrength',
        'aboutBackgroundDecoration',
        'aboutPortraitFrame',
        'aboutDotsEnabled',
        'aboutProfilePhotoDisplayPath',
        'aboutHeaderLocationLine',
        'experienceEntries',
        'experienceEntriesFull',
        'experienceHasSecondaryVisible',
        'educationEntries',
        'educationEntriesFull',
        'educationHasSecondaryVisible',
        'certificationEntriesFull',
        'certificationHasSecondaryVisible',
        'skillsTreePrimary',
        'skillsTreeFull',
        'skillsHasSecondaryVisible',
        'flagshipProjects',
        'flagshipProjectsFull',
        'flagshipProjectsHasSecondaryVisible',
        InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE,
        CertificationContract::KEY_ENTRIES_BY_LOCALE,
        'referenceEntries',
        'situationContent',
        'aboutProfilePhotoXPercent',
        'aboutProfilePhotoWidthPx',
        'aboutProfilePhotoWidthPercent',
        'aboutProfilePhotoHeightPercent',
        'aboutProfilePhotoShadowEnabled',
        'aboutProfilePhotoShadowColor',
        'aboutProfilePhotoShadowOpacity',
        'aboutProfilePhotoShadowOffsetX',
        'aboutProfilePhotoShadowOffsetY',
        'aboutProfilePhotoShadowBlur',
        'aboutProfilePhotoShadowSpread',
        'aboutProfilePhotoRingScale',
        'aboutProfilePhotoSubjectXPercent',
        'aboutProfilePhotoSubjectYPercent',
        'aboutProfilePhotoRingThicknessPx',
        'aboutDiskEnabled',
        'aboutDiskScale',
        'aboutDiskOpacity',
        'aboutDiskSubjectX',
        'aboutDiskSubjectY',
        'aboutDiskBorderThicknessPx',
        'aboutDiskBorderOpacity',
        'aboutDiskGlowOuterOpacity',
        'aboutDiskGlowInnerOpacity',
        'aboutDiskGlowOuterBlurPx',
        'aboutDiskGlowInnerBlurPx',
        'aboutDiskColorInner',
        'aboutDiskColorOuter',
        'aboutDiskBorderColor',
    ];

    /**
     * @var list<string> Prefixes for obsolete About decoration keys still found in legacy rows.
     */
    private const OBSOLETE_KEY_PREFIXES = [
        'aboutDots',
        'aboutBgDecor',
    ];

    /**
     * @brief Reduce a decoded profile payload to the persisted whitelist and normalize supported contracts.
     *
     * @param array<string, mixed> $payload Raw or merged profile content JSON.
     * @return array<string, mixed> Sanitized payload safe for database storage and backup export.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function sanitizeForPersistence(array $payload): array
    {
        self::migrateInterestsLegacyPayload($payload);
        self::migrateCertificationLegacyPayload($payload);
        self::stripObsoleteKeys($payload);

        $sanitized = [];
        foreach (self::PERSISTED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $sanitized[$key] = $payload[$key];
        }

        $sanitized = SectionBackgroundContract::applyNormalizedMapToPayload($sanitized);
        $sanitized[AboutSectionPatternCustomizationContract::KEY] = AboutSectionPatternCustomizationContract::normalize(
            $sanitized[AboutSectionPatternCustomizationContract::KEY]
            ?? AboutSectionPatternCustomizationContract::fromPayload($sanitized)
        );

        if (array_key_exists(AboutPresentationTypographyContract::KEY, $sanitized)) {
            $sanitized[AboutPresentationTypographyContract::KEY] = AboutPresentationTypographyContract::fromPayload($sanitized);
        }

        if (array_key_exists(FlagshipProjectsContract::KEY_SECTION_ENABLED, $sanitized)) {
            $sanitized[FlagshipProjectsContract::KEY_SECTION_ENABLED] = FlagshipProjectsContract::normalizeEnabled(
                $sanitized[FlagshipProjectsContract::KEY_SECTION_ENABLED]
            );
        }

        if (array_key_exists(ReferencesContract::KEY_SECTION_ENABLED, $sanitized)) {
            $sanitized[ReferencesContract::KEY_SECTION_ENABLED] = ReferencesContract::normalizeEnabled(
                $sanitized[ReferencesContract::KEY_SECTION_ENABLED]
            );
        }

        if (array_key_exists(CvPencilDecorationContract::KEY, $sanitized)) {
            $sanitized[CvPencilDecorationContract::KEY] = CvPencilDecorationContract::normalize(
                $sanitized[CvPencilDecorationContract::KEY]
            );
        }

        if (array_key_exists(FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE, $sanitized)) {
            $rawProjects = $sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE];
            if (is_array($rawProjects)) {
                $normalizedProjects = FlagshipProjectsContract::normalizeEntriesByLocale($rawProjects);
                if ($normalizedProjects !== null) {
                    $sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE] = $normalizedProjects;
                } else {
                    unset($sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]);
                }
            } else {
                unset($sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]);
            }
        }

        if (array_key_exists(ExperienceContract::KEY_ENTRIES_BY_LOCALE, $sanitized)) {
            $rawExperience = $sanitized[ExperienceContract::KEY_ENTRIES_BY_LOCALE];
            if (is_array($rawExperience)) {
                $normalizedExperience = ExperienceContract::normalizeEntriesByLocale($rawExperience);
                if ($normalizedExperience !== null) {
                    $sanitized[ExperienceContract::KEY_ENTRIES_BY_LOCALE] = $normalizedExperience;
                } else {
                    unset($sanitized[ExperienceContract::KEY_ENTRIES_BY_LOCALE]);
                }
            } else {
                unset($sanitized[ExperienceContract::KEY_ENTRIES_BY_LOCALE]);
            }
        }

        if (array_key_exists(EducationContract::KEY_ENTRIES_BY_LOCALE, $sanitized)) {
            $rawEducation = $sanitized[EducationContract::KEY_ENTRIES_BY_LOCALE];
            if (is_array($rawEducation)) {
                $normalizedEducation = EducationContract::normalizeEntriesByLocale($rawEducation);
                if ($normalizedEducation !== null) {
                    $sanitized[EducationContract::KEY_ENTRIES_BY_LOCALE] = $normalizedEducation;
                } else {
                    unset($sanitized[EducationContract::KEY_ENTRIES_BY_LOCALE]);
                }
            } else {
                unset($sanitized[EducationContract::KEY_ENTRIES_BY_LOCALE]);
            }
        }

        if (array_key_exists(CertificationContract::KEY_ENTRIES, $sanitized)) {
            $rawCertification = $sanitized[CertificationContract::KEY_ENTRIES];
            if (is_array($rawCertification)) {
                $sanitized[CertificationContract::KEY_ENTRIES] = CertificationContract::sanitizePersistedEntries(array_values($rawCertification));
            } else {
                unset($sanitized[CertificationContract::KEY_ENTRIES]);
            }
        }

        if (array_key_exists(LanguagesContract::KEY_ENTRIES, $sanitized)) {
            $rawLanguages = $sanitized[LanguagesContract::KEY_ENTRIES];
            if (is_array($rawLanguages)) {
                $sanitized[LanguagesContract::KEY_ENTRIES] = LanguagesContract::sanitizePersistedEntries(array_values($rawLanguages));
            } else {
                unset($sanitized[LanguagesContract::KEY_ENTRIES]);
            }
        }

        if (array_key_exists(InterestsContract::KEY_ENTRIES, $sanitized)) {
            $rawInterests = $sanitized[InterestsContract::KEY_ENTRIES];
            if (is_array($rawInterests)) {
                $sanitized[InterestsContract::KEY_ENTRIES] = InterestsContract::sanitizePersistedEntries(array_values($rawInterests));
            } else {
                unset($sanitized[InterestsContract::KEY_ENTRIES]);
            }
        }

        if (array_key_exists(InterestsContract::KEY_COLUMNS_PER_ROW, $sanitized)) {
            $sanitized[InterestsContract::KEY_COLUMNS_PER_ROW] = InterestsContract::normalizeColumnsPerRow(
                $sanitized[InterestsContract::KEY_COLUMNS_PER_ROW]
            );
        }

        if (array_key_exists(WebProfilesContract::KEY_ENTRIES, $sanitized)) {
            $rawWebProfiles = $sanitized[WebProfilesContract::KEY_ENTRIES];
            if (is_array($rawWebProfiles)) {
                $normalizedWebProfiles = WebProfilesContract::normalizeEntries(array_values($rawWebProfiles));
                if ($normalizedWebProfiles !== null) {
                    $sanitized[WebProfilesContract::KEY_ENTRIES] = $normalizedWebProfiles;
                } else {
                    unset($sanitized[WebProfilesContract::KEY_ENTRIES]);
                }
            } else {
                unset($sanitized[WebProfilesContract::KEY_ENTRIES]);
            }
        }

        if (array_key_exists(ReferencesContract::KEY_ENTRIES_BY_LOCALE, $sanitized)) {
            $rawReferences = $sanitized[ReferencesContract::KEY_ENTRIES_BY_LOCALE];
            if (is_array($rawReferences)) {
                $normalizedReferences = ReferencesContract::normalizeEntriesByLocale($rawReferences);
                if ($normalizedReferences !== null) {
                    $sanitized[ReferencesContract::KEY_ENTRIES_BY_LOCALE] = $normalizedReferences;
                } else {
                    unset($sanitized[ReferencesContract::KEY_ENTRIES_BY_LOCALE]);
                }
            } else {
                unset($sanitized[ReferencesContract::KEY_ENTRIES_BY_LOCALE]);
            }
        }

        if (array_key_exists(SkillsTreeContract::KEY, $sanitized)) {
            $normalizedCatalog = SkillsTreeContract::normalizeCatalog(
                $sanitized[SkillsTreeContract::KEY],
                ['fr', 'en', 'de', 'lt', 'no'],
                'fr'
            );
            if ($normalizedCatalog !== null) {
                $sanitized[SkillsTreeContract::KEY] = $normalizedCatalog;
            } else {
                unset($sanitized[SkillsTreeContract::KEY]);
            }
        }

        return $sanitized;
    }

    /**
     * @brief Migrate legacy per-locale interest lists into canonical `interestEntries`.
     *
     * @param array<string, mixed> $payload Profile content JSON array mutated in place.
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    private static function migrateInterestsLegacyPayload(array &$payload): void
    {
        if (array_key_exists(InterestsContract::KEY_ENTRIES, $payload)) {
            return;
        }

        $legacy = $payload[InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($legacy)) {
            return;
        }

        $activeLocales = array_values(array_filter(array_keys($legacy), static fn (mixed $locale): bool => is_string($locale) && $locale !== ''));
        if ($activeLocales === []) {
            $activeLocales = ['fr'];
        }

        $defaultLocale = in_array('fr', $activeLocales, true) ? 'fr' : $activeLocales[0];
        $payload[InterestsContract::KEY_ENTRIES] = InterestsContract::migrateLegacyEntriesByLocale(
            $legacy,
            $activeLocales,
            $defaultLocale,
        );
    }

    /**
     * @brief Migrate legacy per-locale certification lists into canonical `certificationEntries`.
     *
     * @param array<string, mixed> $payload Profile content JSON array mutated in place.
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    private static function migrateCertificationLegacyPayload(array &$payload): void
    {
        if (array_key_exists(CertificationContract::KEY_ENTRIES, $payload)) {
            return;
        }

        $legacy = $payload[CertificationContract::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($legacy)) {
            return;
        }

        $activeLocales = array_values(array_filter(array_keys($legacy), static fn (mixed $locale): bool => is_string($locale) && $locale !== ''));
        if ($activeLocales === []) {
            $activeLocales = ['fr'];
        }

        $defaultLocale = in_array('fr', $activeLocales, true) ? 'fr' : $activeLocales[0];
        $payload[CertificationContract::KEY_ENTRIES] = CertificationContract::migrateLegacyEntriesByLocale(
            $legacy,
            $activeLocales,
            $defaultLocale,
        );
    }

    /**
     * @brief Remove obsolete keys from a payload without rebuilding the whitelist.
     *
     * @param array<string, mixed> $payload Profile content JSON array mutated in place.
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function stripObsoleteKeys(array &$payload): void
    {
        foreach (self::OBSOLETE_TOP_LEVEL_KEYS as $key) {
            unset($payload[$key]);
        }

        foreach (array_keys($payload) as $key) {
            if (!is_string($key)) {
                continue;
            }

            foreach (self::OBSOLETE_KEY_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    unset($payload[$key]);
                    break;
                }
            }
        }
    }
}
