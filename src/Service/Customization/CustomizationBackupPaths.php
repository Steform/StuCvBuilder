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

    public const DATA_COMPANY_CV_SECTION_OVERRIDES = 'data/company_cv_section_overrides.json';

    public const DATA_COMPANY_CV_VISITS = 'data/company_cv_visits.json';

    public const DATA_CV_CONNECTION_LOGS = 'data/cv_connection_logs.json';

    public const FILES_PREFIX = 'files/';

    public const EMPLOYMENT_FILES_PREFIX = 'employment_files/';

    public const FORMAT_VERSION = 2;

    /**
     * @return list<int>
     */
    public static function supportedFormatVersions(): array
    {
        return [1, self::FORMAT_VERSION];
    }

    /**
     * @return list<string> Employment JSON paths required for format version 2 archives.
     */
    public static function employmentDataPaths(): array
    {
        return [
            self::DATA_EMPLOYMENT_COUNTRIES,
            self::DATA_EMPLOYMENT_PRINT_PLACEMENTS,
            self::DATA_EMPLOYMENT_DOCUMENT_VARIANTS,
            self::DATA_TRACKED_COMPANIES,
            self::DATA_COMPANY_CV_SECTION_OVERRIDES,
            self::DATA_COMPANY_CV_VISITS,
            self::DATA_CV_CONNECTION_LOGS,
        ];
    }
}
