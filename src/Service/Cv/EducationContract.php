<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * JSON keys, bounds, and parsing helpers for CV education entries stored under CvProfile content_json.
 */
final class EducationContract
{
    public const KEY_ENTRIES_BY_LOCALE = 'educationEntriesByLocale';

    public const MAX_ENTRIES_PER_LOCALE = 30;

    public const MAX_HIGHLIGHTS_PER_ENTRY = 20;

    public const MAX_TITLE_LENGTH = 200;

    public const MAX_INSTITUTION_NAME_LENGTH = 200;

    public const MAX_HIGHLIGHT_LENGTH = 500;

    public const MAX_WEBSITE_URL_LENGTH = 500;

    public const MAX_LOCATION_LENGTH = 200;

    public const EDUCATION_LOGO_PATH_PREFIX = 'images/cv/education/custom/';

    private const YEAR_MONTH_PATTERN = '/^\d{4}-\d{2}$/';

    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @brief Parse and normalize education entries from admin POST for all active locales.
     *
     * @param Request $request HTTP request with nested `education_entries` array.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array<string, list<array<string, mixed>>>|null Null when validation fails.
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function parseEntriesFromRequest(Request $request, array $activeLocales): ?array
    {
        $raw = self::parseRawEntriesFromRequest($request, $activeLocales);
        if ($raw === null) {
            return null;
        }

        return self::normalizeEntriesByLocale($raw);
    }

    /**
     * @brief Parse raw education rows from admin POST without final normalization (for logo merge first).
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array<string, list<array<string, mixed>>>|null Null when structure is invalid.
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function parseRawEntriesFromRequest(Request $request, array $activeLocales): ?array
    {
        $raw = $request->request->all('education_entries');
        if (!is_array($raw)) {
            return null;
        }

        $result = [];
        foreach ($activeLocales as $locale) {
            $localeRows = $raw[$locale] ?? null;
            if (!is_array($localeRows)) {
                $result[$locale] = [];

                continue;
            }

            $rows = [];
            foreach ($localeRows as $row) {
                if (!is_array($row)) {
                    return null;
                }

                $rows[] = $row;
            }

            if (count($rows) > self::MAX_ENTRIES_PER_LOCALE) {
                return null;
            }

            $result[$locale] = $rows;
        }

        return $result;
    }

    /**
     * @brief Normalize all locale entry lists after logo paths are resolved.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Raw rows keyed by locale.
     * @return array<string, list<array<string, mixed>>>|null Null when any entry is invalid.
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function normalizeEntriesByLocale(array $rowsByLocale): ?array
    {
        $result = [];
        foreach ($rowsByLocale as $locale => $rows) {
            if (!is_string($locale) || !is_array($rows)) {
                continue;
            }

            $normalized = [];
            $sortOrder = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    return null;
                }

                $entry = self::normalizeEntry($row, $sortOrder);
                if ($entry === null) {
                    return null;
                }

                $normalized[] = $entry;
                ++$sortOrder;
            }

            $result[$locale] = $normalized;
        }

        return self::syncIsPrimaryAcrossLocales($result);
    }

    /**
     * @brief Keep isPrimary aligned across locales for the same timeline slot (sortOrder).
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed education rows.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function syncIsPrimaryAcrossLocales(array $entriesByLocale): array
    {
        $isPrimaryBySortOrder = [];
        foreach ($entriesByLocale as $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $sortOrder = (int) ($entry['sortOrder'] ?? 0);
                $isPrimaryBySortOrder[$sortOrder] = ($isPrimaryBySortOrder[$sortOrder] ?? true)
                    && (($entry['isPrimary'] ?? true) === true);
            }
        }

        $synced = [];
        foreach ($entriesByLocale as $locale => $entries) {
            if (!is_string($locale) || !is_array($entries)) {
                continue;
            }

            $localeEntries = [];
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $sortOrder = (int) ($entry['sortOrder'] ?? 0);
                $entry['isPrimary'] = $isPrimaryBySortOrder[$sortOrder] ?? true;
                $localeEntries[] = $entry;
            }

            $synced[$locale] = $localeEntries;
        }

        return $synced;
    }

    /**
     * @brief Normalize a single education entry from request or stored JSON.
     *
     * @param array<string, mixed> $row Raw entry row.
     * @param int $sortOrder Display order index.
     * @param string|null $resolvedLogoPath Logo path after upload merge; falls back to row value when null.
     * @return array<string, mixed>|null Null when invalid.
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function normalizeEntry(array $row, int $sortOrder, ?string $resolvedLogoPath = null): ?array
    {
        $id = isset($row['id']) && is_string($row['id']) && $row['id'] !== ''
            ? trim($row['id'])
            : self::generateUuidV4();
        if (!self::isValidUuid($id)) {
            return null;
        }

        $startDate = self::normalizeYearMonth($row['startDate'] ?? null);
        if ($startDate === null) {
            return null;
        }

        $isCurrent = self::normalizeBool($row['isCurrent'] ?? false);
        $endDate = $isCurrent ? null : self::normalizeYearMonth($row['endDate'] ?? null);
        if (!$isCurrent && $endDate === null) {
            return null;
        }

        if (!$isCurrent && $endDate !== null && $endDate < $startDate) {
            return null;
        }

        $title = self::normalizeText($row['title'] ?? null, self::MAX_TITLE_LENGTH);
        if ($title === null || $title === '') {
            return null;
        }

        $logoPath = $resolvedLogoPath ?? self::normalizeStoredLogoPath($row['institutionLogoPath'] ?? null);

        $institutionNameRaw = self::normalizeText($row['institutionName'] ?? null, self::MAX_INSTITUTION_NAME_LENGTH);
        if ($institutionNameRaw === null) {
            return null;
        }

        $institutionName = $institutionNameRaw;
        if ($institutionName === '' && $logoPath === null) {
            return null;
        }

        $websiteUrl = self::normalizeWebsiteUrl($row['institutionWebsiteUrl'] ?? null);

        $location = self::normalizeOptionalLocation($row['location'] ?? null);

        $highlights = self::normalizeHighlights($row['highlights'] ?? null);
        if ($highlights === null) {
            return null;
        }

        return [
            'id' => $id,
            'sortOrder' => max(0, $sortOrder),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isCurrent' => $isCurrent,
            'title' => $title,
            'institutionName' => $institutionName,
            'institutionWebsiteUrl' => $websiteUrl,
            'location' => $location,
            'institutionLogoPath' => $logoPath,
            'hideInstitutionName' => self::normalizeBool($row['hideInstitutionName'] ?? false),
            'highlights' => $highlights,
            'isPrimary' => self::resolveIsPrimaryFromRow($row),
        ];
    }

    /**
     * @brief Resolve primary CV placement from request or stored row, ignoring removed isVisible legacy key.
     *
     * @param array<string, mixed> $row Raw entry row.
     * @return bool
     * @date 2026-05-31
     * @author Stephane H.
     */
    private static function resolveIsPrimaryFromRow(array $row): bool
    {
        if (array_key_exists('isPrimary', $row)) {
            return self::normalizeBool($row['isPrimary']);
        }

        if (array_key_exists('isVisible', $row) && !self::normalizeBool($row['isVisible'])) {
            return false;
        }

        return true;
    }

    /**
     * @brief Validate stored relative logo path for education custom uploads.
     *
     * @param mixed $value Raw path from JSON or hidden field.
     * @return string|null Normalized path or null when absent/invalid.
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function normalizeStoredLogoPath(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || !str_starts_with($trimmed, self::EDUCATION_LOGO_PATH_PREFIX)) {
            return null;
        }

        if (str_contains($trimmed, '..')) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @brief Read entries map from decoded CvProfile payload.
     *
     * @param array<string, mixed> $payload Decoded JSON.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function entriesByLocaleFromStoredPayload(array $payload): array
    {
        $raw = $payload[self::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $locale => $rows) {
            if (!is_string($locale) || !is_array($rows)) {
                continue;
            }

            $normalized = [];
            $sortOrder = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $entry = self::normalizeEntry($row, is_int($row['sortOrder'] ?? null) ? (int) $row['sortOrder'] : $sortOrder);
                if ($entry !== null) {
                    $normalized[] = $entry;
                    ++$sortOrder;
                }
            }

            usort($normalized, static fn (array $a, array $b): int => ($a['sortOrder'] <=> $b['sortOrder']));
            $result[$locale] = $normalized;
        }

        return $result;
    }

    /**
     * @brief Whether persisted JSON already contains the education map key (admin has saved at least once).
     *
     * @param array<string, mixed> $payload Decoded JSON.
     * @return bool
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function hasPersistedEducationMap(array $payload): bool
    {
        return array_key_exists(self::KEY_ENTRIES_BY_LOCALE, $payload);
    }

    /**
     * @brief Generate a random RFC 4122 version-4 UUID without external dependencies.
     *
     * @return string
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return self::formatUuidBytes($bytes);
    }

    /**
     * @brief Build a stable UUID-shaped identifier from a seed string (for YAML fallback rows).
     *
     * @param string $seed Deterministic seed (locale + entry key).
     * @return string
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function generateDeterministicUuid(string $seed): string
    {
        return \App\Service\Uuid\DeterministicUuidFactory::generate('cv-education', $seed);
    }

    /**
     * @brief Validate RFC 4122 UUID syntax.
     *
     * @param string $value Candidate UUID.
     * @return bool
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function isValidUuid(string $value): bool
    {
        return preg_match(self::UUID_V4_PATTERN, $value) === 1;
    }

    /**
     * @brief Format 16 raw bytes as hyphenated lowercase UUID.
     *
     * @param string $bytes Exactly 16 bytes.
     * @return string
     */
    private static function formatUuidBytes(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * @brief Format YYYY-MM for display (year only when month is 01 and not needed — keep full for consistency).
     *
     * @param string $yearMonth ISO year-month.
     * @return string
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function formatYearMonthForDisplay(string $yearMonth): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $matches)) {
            return $yearMonth;
        }

        $year = $matches[1];
        $month = (int) $matches[2];
        if ($month <= 1) {
            return $year;
        }

        return sprintf('%s/%02d', $year, $month);
    }

    /**
     * @param mixed $value Raw value.
     * @return string|null
     */
    private static function normalizeYearMonth(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $trimmed)) {
            $trimmed .= '-01';
        }

        if (!preg_match(self::YEAR_MONTH_PATTERN, $trimmed)) {
            return null;
        }

        $month = (int) substr($trimmed, 5, 2);
        if ($month < 1 || $month > 12) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param mixed $value Raw value.
     * @return string|null
     */
    private static function normalizeText(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim(strip_tags($value));
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) > $maxLength) {
            $trimmed = mb_substr($trimmed, 0, $maxLength);
        }

        return $trimmed;
    }

    /**
     * @param mixed $value Raw value.
     * @return bool
     */
    private static function normalizeBool(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::normalizeBool($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }

    /**
     * @param mixed $value Raw value.
     * @return string|null Empty string when absent; null when invalid.
     */
    private static function normalizeWebsiteUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_WEBSITE_URL_LENGTH) {
            return null;
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) ? $trimmed : null;
    }

    /**
     * @brief Normalize optional location label (city, campus, country).
     *
     * @param mixed $value Raw location from admin POST or stored JSON.
     * @return string Empty string when absent or invalid.
     * @date 2026-06-08
     * @author Stephane H.
     */
    private static function normalizeOptionalLocation(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim(strip_tags($value));
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) > self::MAX_LOCATION_LENGTH) {
            $trimmed = mb_substr($trimmed, 0, self::MAX_LOCATION_LENGTH);
        }

        return $trimmed;
    }

    /**
     * @param mixed $value Raw highlights.
     * @return list<string>|null
     */
    private static function normalizeHighlights(mixed $value): ?array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            return null;
        }

        $result = [];
        foreach ($value as $line) {
            if (!is_string($line)) {
                continue;
            }

            $trimmed = trim(strip_tags($line));
            if ($trimmed === '') {
                continue;
            }

            if (mb_strlen($trimmed) > self::MAX_HIGHLIGHT_LENGTH) {
                $trimmed = mb_substr($trimmed, 0, self::MAX_HIGHLIGHT_LENGTH);
            }

            $result[] = $trimmed;
            if (count($result) > self::MAX_HIGHLIGHTS_PER_ENTRY) {
                return null;
            }
        }

        return $result;
    }
}
