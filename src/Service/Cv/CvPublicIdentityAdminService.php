<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Service\RichText\RichHtmlSanitizer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Admin read/write helpers for `cvPublicIdentity` stored in CvProfile JSON.
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
final class CvPublicIdentityAdminService
{
    public function __construct(
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
    ) {
    }

    /**
     * @brief Extract stored `cvPublicIdentity` map from profile JSON.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @return array<string, mixed>
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function extractStoredIdentityMap(string $contentJson): array
    {
        $payload = $this->decodeJsonPayload($contentJson);
        $raw = $payload[CvPublicIdentityContract::KEY_ROOT] ?? null;

        return is_array($raw) ? $raw : [];
    }

    /**
     * @brief Extract `cvPublicIdentity` fields for the admin form with safe defaults.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @param list<string> $activeLocales Active locale codes for locale maps.
     * @return array{displayName: string, birthDate: string, birthDateForm: string, city: string, region: string, careerStartYear: string, taglineByLocale: array<string, string>, countryByLocale: array<string, string>, soughtPositionByLocale: array<string, string>, statusByLocale: array<string, string>}
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function extractForAdmin(string $contentJson, array $activeLocales): array
    {
        $localeDefaults = [];
        foreach ($activeLocales as $loc) {
            $localeDefaults[$loc] = '';
        }

        $empty = [
            'displayName' => '',
            'birthDate' => '',
            'birthDateForm' => '',
            'city' => '',
            'region' => '',
            'careerStartYear' => '',
            'taglineByLocale' => $localeDefaults,
            'countryByLocale' => $localeDefaults,
            'soughtPositionByLocale' => $localeDefaults,
            'statusByLocale' => $localeDefaults,
        ];

        $raw = $this->extractStoredIdentityMap($contentJson);
        if ($raw === []) {
            return $empty;
        }

        $displayName = $raw[CvPublicIdentityContract::FIELD_DISPLAY_NAME] ?? '';
        $city = $raw[CvPublicIdentityContract::FIELD_CITY] ?? '';
        $region = $raw[CvPublicIdentityContract::FIELD_REGION] ?? '';
        $birthDate = $raw[CvPublicIdentityContract::FIELD_BIRTH_DATE] ?? '';
        $career = $raw[CvPublicIdentityContract::FIELD_CAREER_START_YEAR] ?? '';

        $taglineByLocale = $this->normalizeLocaleStringMap(
            $raw[CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE] ?? null,
            $activeLocales
        );
        foreach ($activeLocales as $loc) {
            if ($taglineByLocale[$loc] !== '') {
                $taglineByLocale[$loc] = $this->richHtmlSanitizer->sanitize($taglineByLocale[$loc]);
            }
        }

        $countryByLocale = $this->normalizeLocaleStringMap(
            $raw[CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE] ?? null,
            $activeLocales
        );
        $soughtPositionByLocale = $this->normalizeLocaleStringMap(
            $raw[CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE] ?? null,
            $activeLocales
        );
        $statusByLocale = $this->normalizeLocaleStringMap(
            $raw[CvPublicIdentityContract::FIELD_STATUS_BY_LOCALE] ?? null,
            $activeLocales
        );

        $careerStr = '';
        if (is_int($career)) {
            $careerStr = (string) $career;
        } elseif (is_string($career) && $career !== '') {
            $careerStr = $career;
        }

        $birthDateStr = is_string($birthDate) ? $birthDate : '';

        return [
            'displayName' => is_string($displayName) ? $displayName : '',
            'birthDate' => $birthDateStr,
            'birthDateForm' => $this->formatBirthDateForFrenchForm($birthDateStr),
            'city' => is_string($city) ? $city : '',
            'region' => is_string($region) ? $region : '',
            'careerStartYear' => $careerStr,
            'taglineByLocale' => $taglineByLocale,
            'countryByLocale' => $countryByLocale,
            'soughtPositionByLocale' => $soughtPositionByLocale,
            'statusByLocale' => $statusByLocale,
        ];
    }

    /**
     * @brief Parse CV data tab POST (globals + per-locale maps) into a full identity map.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Allowed locale keys.
     * @param array<string, mixed> $existingIdentity Previously stored identity map.
     * @return array<string, mixed>|null Normalized identity map or null on invalid input.
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function parseFromCvDataRequest(Request $request, array $activeLocales, array $existingIdentity): ?array
    {
        $displayName = trim((string) $request->request->get('cv_public_identity_display_name', ''));
        if (strlen($displayName) > CvPublicIdentityContract::DISPLAY_NAME_MAX_LENGTH) {
            return null;
        }

        $city = trim((string) $request->request->get('cv_public_identity_city', ''));
        $region = trim((string) $request->request->get('cv_public_identity_region', ''));
        if (strlen($city) > CvPublicIdentityContract::CITY_MAX_LENGTH || strlen($region) > CvPublicIdentityContract::REGION_MAX_LENGTH) {
            return null;
        }

        $birthRaw = trim((string) $request->request->get('cv_public_identity_birth_date', ''));
        $birthDate = null;
        if ($birthRaw !== '') {
            $parsedBirth = $this->parseBirthDateForStorage($birthRaw);
            if ($parsedBirth === false) {
                return null;
            }

            $birthDate = $parsedBirth;
        }

        $careerRaw = trim((string) $request->request->get('cv_public_identity_career_start_year', ''));
        $careerStartYear = null;
        if ($careerRaw !== '') {
            $year = filter_var($careerRaw, FILTER_VALIDATE_INT);
            $maxYear = (int) (new \DateTimeImmutable('now'))->format('Y');
            if ($year === false || $year < CvPublicIdentityContract::CAREER_START_YEAR_MIN || $year > $maxYear) {
                return null;
            }

            $careerStartYear = $year;
        }

        $countryByLocale = $this->parseLocaleStringMapFromRequest(
            $request->request->all('cv_public_identity_country'),
            $activeLocales,
            CvPublicIdentityContract::COUNTRY_MAX_LENGTH
        );
        if ($countryByLocale === null) {
            return null;
        }

        $soughtPositionByLocale = $this->parseLocaleStringMapFromRequest(
            $request->request->all('cv_public_identity_sought_position'),
            $activeLocales,
            CvPublicIdentityContract::SOUGHT_POSITION_MAX_LENGTH
        );
        if ($soughtPositionByLocale === null) {
            return null;
        }

        $statusByLocale = $this->parseLocaleStringMapFromRequest(
            $request->request->all('cv_public_identity_status'),
            $activeLocales,
            CvPublicIdentityContract::STATUS_MAX_LENGTH
        );
        if ($statusByLocale === null) {
            return null;
        }

        $taglineSubmitted = $request->request->all('cv_public_identity_tagline');
        if (!is_array($taglineSubmitted)) {
            return null;
        }

        $taglineByLocale = $this->normalizeLocaleStringMap(
            $existingIdentity[CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE] ?? null,
            $activeLocales
        );
        foreach ($activeLocales as $localeCode) {
            $rawTagline = $taglineSubmitted[$localeCode] ?? '';
            if (!is_string($rawTagline)) {
                return null;
            }

            $sanitizedTagline = $this->richHtmlSanitizer->sanitize(trim($rawTagline));
            if ($this->measureTaglinePlainTextLength($sanitizedTagline) > CvPublicIdentityContract::TAGLINE_MAX_LENGTH) {
                return null;
            }

            $taglineByLocale[$localeCode] = $sanitizedTagline;
        }

        $out = [
            CvPublicIdentityContract::FIELD_DISPLAY_NAME => $displayName,
            CvPublicIdentityContract::FIELD_CITY => $city,
            CvPublicIdentityContract::FIELD_REGION => $region,
            CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE => $taglineByLocale,
            CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE => $countryByLocale,
            CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE => $soughtPositionByLocale,
            CvPublicIdentityContract::FIELD_STATUS_BY_LOCALE => $statusByLocale,
        ];

        if ($birthDate !== null) {
            $out[CvPublicIdentityContract::FIELD_BIRTH_DATE] = $birthDate;
        } elseif (is_string($existingIdentity[CvPublicIdentityContract::FIELD_BIRTH_DATE] ?? null)) {
            $out[CvPublicIdentityContract::FIELD_BIRTH_DATE] = $existingIdentity[CvPublicIdentityContract::FIELD_BIRTH_DATE];
        }

        if ($careerStartYear !== null) {
            $out[CvPublicIdentityContract::FIELD_CAREER_START_YEAR] = $careerStartYear;
        } elseif (array_key_exists(CvPublicIdentityContract::FIELD_CAREER_START_YEAR, $existingIdentity)) {
            $out[CvPublicIdentityContract::FIELD_CAREER_START_YEAR] = $existingIdentity[CvPublicIdentityContract::FIELD_CAREER_START_YEAR];
        }

        return $out;
    }

    /**
     * @brief Parse a per-locale string map from request with max length per value.
     *
     * @param mixed $submitted Raw request map.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param int $maxLength Maximum UTF-8 length per value.
     * @return array<string, string>|null Normalized map or null when invalid.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function parseLocaleStringMapFromRequest(mixed $submitted, array $activeLocales, int $maxLength): ?array
    {
        if (!is_array($submitted)) {
            return null;
        }

        $map = $this->normalizeLocaleStringMap(null, $activeLocales);
        foreach ($activeLocales as $localeCode) {
            $raw = $submitted[$localeCode] ?? '';
            if (!is_string($raw)) {
                return null;
            }

            $value = trim($raw);
            if (strlen($value) > $maxLength) {
                return null;
            }

            $map[$localeCode] = $value;
        }

        return $map;
    }

    /**
     * @brief Normalize a locale map to contain every active locale key.
     *
     * @param mixed $raw Stored locale map.
     * @param list<string> $activeLocales Active locale codes.
     * @return array<string, string>
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function normalizeLocaleStringMap(mixed $raw, array $activeLocales): array
    {
        $defaults = [];
        foreach ($activeLocales as $localeCode) {
            $defaults[$localeCode] = '';
        }

        if (!is_array($raw)) {
            return $defaults;
        }

        foreach ($activeLocales as $localeCode) {
            $value = $raw[$localeCode] ?? '';
            $defaults[$localeCode] = is_string($value) ? trim($value) : '';
        }

        return $defaults;
    }

    /**
     * @brief Format stored `Y-m-d` birth date as `d/m/Y` for the admin form.
     *
     * @param string $ymd Stored date or empty string.
     * @return string `d/m/Y` when valid, otherwise empty string.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function formatBirthDateForFrenchForm(string $ymd): string
    {
        if ($ymd === '') {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        if ($parsed === false || $parsed->format('Y-m-d') !== $ymd) {
            return '';
        }

        return $parsed->format('d/m/Y');
    }

    /**
     * @brief Parse birth date from form: French `d/m/Y` or legacy ISO `Y-m-d`.
     *
     * @param string $birthRaw Raw submitted value.
     * @return string|false|null Canonical `Y-m-d`, `null` when empty, `false` when invalid.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function parseBirthDateForStorage(string $birthRaw): string|false|null
    {
        $birthRaw = trim($birthRaw);
        if ($birthRaw === '') {
            return null;
        }

        $birth = false;
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $birthRaw, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (!checkdate($month, $day, $year)) {
                return false;
            }

            $birth = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birthRaw, $m)) {
            $candidate = \DateTimeImmutable::createFromFormat('Y-m-d', $birthRaw);
            if ($candidate !== false && $candidate->format('Y-m-d') === $birthRaw) {
                $birth = $candidate;
            }
        }

        if ($birth === false) {
            return false;
        }

        $today = new \DateTimeImmutable('today');
        if ($birth > $today) {
            return false;
        }

        return $birth->format('Y-m-d');
    }

    /**
     * @brief Measure visible text length of a tagline fragment for `TAGLINE_MAX_LENGTH`.
     *
     * @param string $fragment Sanitized HTML or plain text.
     * @return int Character count.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function measureTaglinePlainTextLength(string $fragment): int
    {
        $plain = trim(html_entity_decode(strip_tags($fragment), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return mb_strlen($plain, 'UTF-8');
    }

    /**
     * @brief Decode JSON payload as associative array.
     *
     * @param string $json JSON payload.
     * @return array<string, mixed>
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function decodeJsonPayload(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
