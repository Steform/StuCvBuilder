<?php

declare(strict_types=1);

namespace App\Service\Customization;

/**
 * @brief Relative paths inside the customization backup ZIP archive.
 */
final class CustomizationBackupPaths
{
    public const MANIFEST = 'manifest.json';

    public const DATA_HOME = 'data/home_customization.json';

    public const DATA_HOME_TRANSLATIONS = 'data/home_customization_translations.json';

    public const DATA_CV_PROFILE = 'data/cv_profile.json';

    public const DATA_LOCALE = 'data/locale_configuration.json';

    public const DATA_EMPLOYMENT_COUNTRIES = 'data/employment_countries.json';

    public const DATA_EMPLOYMENT_PRINT_PLACEMENTS = 'data/employment_print_placements.json';

    public const DATA_EMPLOYMENT_DOCUMENT_VARIANTS = 'data/employment_document_variants.json';

    public const DATA_TRACKED_COMPANIES = 'data/tracked_companies.json';

    /** @var string Legacy v2 path (section overrides); accepted on restore only. */
    public const DATA_COMPANY_CV_SECTION_OVERRIDES = 'data/company_cv_section_overrides.json';

    public const DATA_COMPANY_CV_PROFILES = 'data/company_cv_profiles.json';

    public const DATA_COMPANY_CV_VISITS = 'data/company_cv_visits.json';

    public const DATA_CV_CONNECTION_LOGS = 'data/cv_connection_logs.json';

    public const FILES_PREFIX = 'files/';

    public const EMPLOYMENT_FILES_PREFIX = 'employment_files/';

    public const FORMAT_VERSION = 3;

    /**
     * @return list<int>
     */
    public static function supportedFormatVersions(): array
    {
        return [1, 2, self::FORMAT_VERSION];
    }

    /**
     * @brief Employment JSON paths required for a given archive format version.
     *
     * @param int $formatVersion Manifest format version.
     * @return list<string>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public static function employmentDataPathsForVersion(int $formatVersion): array
    {
        $base = [
            self::DATA_EMPLOYMENT_COUNTRIES,
            self::DATA_EMPLOYMENT_PRINT_PLACEMENTS,
            self::DATA_EMPLOYMENT_DOCUMENT_VARIANTS,
            self::DATA_TRACKED_COMPANIES,
            self::DATA_COMPANY_CV_VISITS,
            self::DATA_CV_CONNECTION_LOGS,
        ];

        if ($formatVersion <= 2) {
            $base[] = self::DATA_COMPANY_CV_SECTION_OVERRIDES;
        } else {
            $base[] = self::DATA_COMPANY_CV_PROFILES;
        }

        return $base;
    }

    /**
     * @return list<string> Employment JSON paths written by the current export format.
     */
    public static function employmentDataPaths(): array
    {
        return self::employmentDataPathsForVersion(self::FORMAT_VERSION);
    }
}
