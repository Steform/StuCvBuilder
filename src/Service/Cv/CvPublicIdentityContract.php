<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * @brief JSON keys and validation bounds for {@see CvPublicIdentityPlaceholderService} input stored under CvProfile `contentJson`.
 *
 * Stored under root key {@see self::KEY_ROOT}. Deep-merge of targeted profile overrides is reserved for a future feature.
 *
 * Token names in rich HTML remain snake_case (`[[cv.display_name]]`, …) while JSON fields use camelCase.
 *
 * @date 2026-05-14
 * @author Stephane H.
 */
final class CvPublicIdentityContract
{
    /** @var string Root object key in CvProfile JSON payload. */
    public const KEY_ROOT = 'cvPublicIdentity';

    /** @var string Resolved company format code for employment PDF links (runtime only, not persisted). */
    public const KEY_EMPLOYMENT_FORMAT_CODE = 'employmentFormatCode';

    public const FIELD_DISPLAY_NAME = 'displayName';

    public const FIELD_BIRTH_DATE = 'birthDate';

    public const FIELD_CITY = 'city';

    public const FIELD_REGION = 'region';

    public const FIELD_COUNTRY_BY_LOCALE = 'countryByLocale';

    public const FIELD_SOUGHT_POSITION_BY_LOCALE = 'soughtPositionByLocale';

    public const FIELD_STATUS_BY_LOCALE = 'statusByLocale';

    public const FIELD_CAREER_START_YEAR = 'careerStartYear';

    public const FIELD_TAGLINE_BY_LOCALE = 'taglineByLocale';

    public const DISPLAY_NAME_MAX_LENGTH = 200;

    public const CITY_MAX_LENGTH = 120;

    public const REGION_MAX_LENGTH = 120;

    public const COUNTRY_MAX_LENGTH = 120;

    public const SOUGHT_POSITION_MAX_LENGTH = 200;

    public const STATUS_MAX_LENGTH = 120;

    public const TAGLINE_MAX_LENGTH = 500;

    public const CAREER_START_YEAR_MIN = 1950;

    /** @brief Obvious placeholder age when birth date is missing (About presentation `[[cv.age_years]]` only). */
    public const PRESENTATION_FALLBACK_AGE_YEARS = 100;

    /** @brief Obvious placeholder experience span when career start year cannot be computed (About presentation `[[cv.experience_years]]` only). */
    public const PRESENTATION_FALLBACK_EXPERIENCE_YEARS = 200;

    /**
     * @brief Whitelisted snake_case token names (without `[[cv.` / `]]`) for About HTML and CKEditor insert UI.
     *
     * The `pdf`, `lm_pdf`, and `learn_more` tokens are special: they do not read {@see self::KEY_ROOT}; the server emits fixed link HTML.
     * `sought_position` reads {@see self::FIELD_SOUGHT_POSITION_BY_LOCALE} (localized job target per visitor language).
     *
     * @var list<string>
     */
    public const PLACEHOLDER_TOKEN_NAMES = [
        'display_name',
        'age_years',
        'city',
        'region',
        'country',
        'sought_position',
        'status',
        'career_start_year',
        'experience_years',
        'tagline',
        'document_year',
        'date_now',
        'pdf',
        'lm_pdf',
        'learn_more',
    ];
}
