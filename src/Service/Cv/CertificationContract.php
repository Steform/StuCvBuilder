<?php



declare(strict_types=1);



namespace App\Service\Cv;



use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Uid\Uuid;



/**

 * JSON keys, bounds, and parsing helpers for CV certification entries stored under CvProfile content_json.

 */

final class CertificationContract

{

    public const KEY_ENTRIES = 'certificationEntries';



    /** @var string Legacy per-locale map migrated on read and stripped on persist. */

    public const KEY_ENTRIES_BY_LOCALE = 'certificationEntriesByLocale';



    public const MAX_ENTRIES = 30;



    /** @deprecated Use {@see MAX_ENTRIES}. */

    public const MAX_ENTRIES_PER_LOCALE = 30;



    public const MAX_HIGHLIGHTS_PER_ENTRY = 20;



    public const MAX_TITLE_LENGTH = 200;



    public const MAX_PROVIDER_NAME_LENGTH = 200;



    public const MAX_HIGHLIGHT_LENGTH = 500;



    public const MAX_WEBSITE_URL_LENGTH = 500;



    public const MAX_LOCATION_LENGTH = 200;



    public const CERTIFICATION_PROOF_PDF_PATH_PREFIX = 'documents/cv/certification/custom/';



    public const MAX_PROOF_PDF_BYTES = 5242880;



    private const YEAR_MONTH_PATTERN = '/^\d{4}-\d{2}$/';



    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';



    /**

     * @brief Parse and normalize certification entries from admin POST.

     *

     * @param Request $request HTTP request with flat `certification_entries` array.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale used for required field validation.

     * @return list<array<string, mixed>>|null Null when validation fails.

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function parseEntriesFromRequest(Request $request, array $activeLocales, string $defaultLocale): ?array

    {

        $raw = self::parseRawEntriesFromRequest($request);

        if ($raw === null) {

            return null;

        }



        return self::normalizeEntries($raw, $activeLocales, $defaultLocale);

    }



    /**

     * @brief Parse raw certification rows from admin POST without final normalization.

     *

     * @param Request $request HTTP request.

     * @return list<array<string, mixed>>|null Null when structure is invalid.

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function parseRawEntriesFromRequest(Request $request): ?array

    {

        $raw = $request->request->all('certification_entries');

        if (!is_array($raw)) {

            return null;

        }



        $rows = [];

        foreach ($raw as $row) {

            if (!is_array($row)) {

                return null;

            }



            $rows[] = $row;

        }



        if (count($rows) > self::MAX_ENTRIES) {

            return null;

        }



        return $rows;

    }



    /**

     * @brief Normalize a list of canonical certification entries.

     *

     * @param list<array<string, mixed>> $rows Raw rows.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return list<array<string, mixed>>|null Null when any entry is invalid.

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function normalizeEntries(array $rows, array $activeLocales, string $defaultLocale): ?array

    {

        $normalized = [];

        $sortOrder = 0;

        foreach ($rows as $row) {

            if (!is_array($row)) {

                return null;

            }



            $entry = self::normalizeEntry($row, $sortOrder, $activeLocales, $defaultLocale);

            if ($entry === null) {

                return null;

            }



            $normalized[] = $entry;

            ++$sortOrder;

        }



        return $normalized;

    }



    /**

     * @brief Normalize one canonical certification entry with localized text fields.

     *

     * @param array<string, mixed> $row Raw row.

     * @param int $sortOrder Display order index.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return array<string, mixed>|null Null when invalid.

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function normalizeEntry(

        array $row,

        int $sortOrder,

        array $activeLocales,

        string $defaultLocale,

    ): ?array {

        $titleByLocale = self::normalizeTextByLocale($row, 'titleByLocale', 'title', $activeLocales, self::MAX_TITLE_LENGTH);

        $providerNameByLocale = self::normalizeTextByLocale(

            $row,

            'providerNameByLocale',

            'providerName',

            $activeLocales,

            self::MAX_PROVIDER_NAME_LENGTH

        );

        $locationByLocale = self::normalizeTextByLocale(

            $row,

            'locationByLocale',

            'location',

            $activeLocales,

            self::MAX_LOCATION_LENGTH,

            allowEmpty: true,

        );

        $highlightsByLocale = self::normalizeHighlightsByLocale($row, $activeLocales);



        if ($highlightsByLocale === null) {

            return null;

        }



        $defaultTitle = $titleByLocale[$defaultLocale] ?? '';

        $defaultProvider = $providerNameByLocale[$defaultLocale] ?? '';

        if ($defaultTitle === '' || $defaultProvider === '') {

            return null;

        }



        $id = isset($row['id']) && is_string($row['id']) && $row['id'] !== ''

            ? trim($row['id'])

            : (string) Uuid::v4();

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

            $endDate = $startDate;

        }



        if (!$isCurrent && $endDate !== null && $endDate < $startDate) {

            return null;

        }



        return [

            'id' => $id,

            'sortOrder' => max(0, $sortOrder),

            'startDate' => $startDate,

            'endDate' => $endDate,

            'isCurrent' => $isCurrent,

            'titleByLocale' => $titleByLocale,

            'providerNameByLocale' => $providerNameByLocale,

            'locationByLocale' => $locationByLocale,

            'providerWebsiteUrl' => self::normalizeWebsiteUrl($row['providerWebsiteUrl'] ?? null),

            'proofPdfPath' => self::normalizeStoredProofPdfPath($row['proofPdfPath'] ?? null),

            'proofUrl' => self::normalizeWebsiteUrl($row['proofUrl'] ?? null),

            'highlightsByLocale' => $highlightsByLocale,

            'isPrimary' => self::resolveIsPrimaryFromRow($row),

        ];

    }



    /**

     * @brief Normalize a legacy per-locale projected row (migration internal use).

     *

     * @param array<string, mixed> $row Raw legacy entry row.

     * @param int $sortOrder Display order index.

     * @return array<string, mixed>|null Null when invalid.

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function normalizeLegacyProjectedEntry(array $row, int $sortOrder): ?array

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

            $endDate = $startDate;

        }



        if (!$isCurrent && $endDate !== null && $endDate < $startDate) {

            return null;

        }



        $title = self::normalizeText($row['title'] ?? null, self::MAX_TITLE_LENGTH);

        if ($title === null || $title === '') {

            return null;

        }



        $providerName = self::normalizeText($row['providerName'] ?? null, self::MAX_PROVIDER_NAME_LENGTH);

        if ($providerName === null || $providerName === '') {

            return null;

        }



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

            'providerName' => $providerName,

            'providerWebsiteUrl' => self::normalizeWebsiteUrl($row['providerWebsiteUrl'] ?? null),

            'location' => self::normalizeOptionalLocation($row['location'] ?? null),

            'proofPdfPath' => self::normalizeStoredProofPdfPath($row['proofPdfPath'] ?? null),

            'proofUrl' => self::normalizeWebsiteUrl($row['proofUrl'] ?? null),

            'highlights' => $highlights,

            'isPrimary' => self::resolveIsPrimaryFromRow($row),

        ];

    }



    /**

     * @brief Read canonical certification entries from decoded CvProfile payload (with legacy migration).

     *

     * @param array<string, mixed> $payload Decoded JSON.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return list<array<string, mixed>>

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function entriesFromStoredPayload(

        array $payload,

        array $activeLocales = ['fr'],

        string $defaultLocale = 'fr',

    ): array {

        $raw = $payload[self::KEY_ENTRIES] ?? null;

        if (is_array($raw)) {

            $normalized = self::normalizeEntries(array_values($raw), $activeLocales, $defaultLocale);



            return $normalized ?? [];

        }



        $legacy = $payload[self::KEY_ENTRIES_BY_LOCALE] ?? null;

        if (!is_array($legacy)) {

            return [];

        }



        return self::migrateLegacyEntriesByLocale($legacy, $activeLocales, $defaultLocale);

    }



    /**

     * @brief Whether persisted certification entries exist in payload.

     *

     * @param array<string, mixed> $payload Decoded JSON.

     * @return bool

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function hasPersistedEntries(array $payload): bool

    {

        if (array_key_exists(self::KEY_ENTRIES, $payload) && is_array($payload[self::KEY_ENTRIES])) {

            return true;

        }



        return array_key_exists(self::KEY_ENTRIES_BY_LOCALE, $payload)

            && is_array($payload[self::KEY_ENTRIES_BY_LOCALE]);

    }



    /**

     * @brief Migrate legacy per-locale lists into canonical entries with localized maps.

     *

     * @param array<string, mixed> $legacyByLocale Legacy map locale => rows.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return list<array<string, mixed>>

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function migrateLegacyEntriesByLocale(

        array $legacyByLocale,

        array $activeLocales,

        string $defaultLocale,

    ): array {

        $rowsBySortOrder = [];

        foreach ($activeLocales as $locale) {

            $rows = $legacyByLocale[$locale] ?? null;

            if (!is_array($rows)) {

                continue;

            }



            foreach ($rows as $index => $row) {

                if (!is_array($row)) {

                    continue;

                }



                $sortOrder = is_int($row['sortOrder'] ?? null) ? (int) $row['sortOrder'] : (int) $index;

                $rowsBySortOrder[$sortOrder][$locale] = $row;

            }

        }



        if ($rowsBySortOrder === []) {

            return [];

        }



        ksort($rowsBySortOrder, SORT_NUMERIC);

        $migrated = [];

        foreach ($rowsBySortOrder as $sortOrder => $localeRows) {

            $titleByLocale = [];

            $providerNameByLocale = [];

            $locationByLocale = [];

            $highlightsByLocale = [];

            $sharedRow = null;

            $id = null;

            $isPrimary = true;



            foreach ($activeLocales as $locale) {

                $row = $localeRows[$locale] ?? null;

                if (!is_array($row)) {

                    continue;

                }



                if ($sharedRow === null || $locale === $defaultLocale) {

                    $sharedRow = $row;

                }



                $candidateId = is_string($row['id'] ?? null) ? trim($row['id']) : '';

                if ($id === null && $candidateId !== '' && self::isValidUuid($candidateId)) {

                    $id = $candidateId;

                }



                $title = is_string($row['title'] ?? null) ? strip_tags(trim($row['title'])) : '';

                if ($title !== '' && mb_strlen($title) <= self::MAX_TITLE_LENGTH) {

                    $titleByLocale[$locale] = $title;

                }



                $provider = is_string($row['providerName'] ?? null) ? strip_tags(trim($row['providerName'])) : '';

                if ($provider !== '' && mb_strlen($provider) <= self::MAX_PROVIDER_NAME_LENGTH) {

                    $providerNameByLocale[$locale] = $provider;

                }



                $location = is_string($row['location'] ?? null) ? strip_tags(trim($row['location'])) : '';

                if ($location !== '' && mb_strlen($location) <= self::MAX_LOCATION_LENGTH) {

                    $locationByLocale[$locale] = $location;

                }



                $highlights = self::normalizeHighlights($row['highlights'] ?? null);

                if ($highlights !== null && $highlights !== []) {

                    $highlightsByLocale[$locale] = $highlights;

                }



                if (array_key_exists('isPrimary', $row)) {

                    $isPrimary = $isPrimary && self::normalizeBool($row['isPrimary']);

                } elseif (array_key_exists('isVisible', $row) && !self::normalizeBool($row['isVisible'])) {

                    $isPrimary = false;

                }

            }



            if ($titleByLocale === [] || $providerNameByLocale === []) {

                continue;

            }



            $sharedRow ??= reset($localeRows) ?: [];

            $entry = self::normalizeEntry(

                [

                    'id' => $id ?? (string) Uuid::v4(),

                    'sortOrder' => count($migrated),

                    'startDate' => $sharedRow['startDate'] ?? null,

                    'endDate' => $sharedRow['endDate'] ?? null,

                    'isCurrent' => $sharedRow['isCurrent'] ?? false,

                    'titleByLocale' => $titleByLocale,

                    'providerNameByLocale' => $providerNameByLocale,

                    'locationByLocale' => $locationByLocale,

                    'providerWebsiteUrl' => $sharedRow['providerWebsiteUrl'] ?? null,

                    'proofPdfPath' => $sharedRow['proofPdfPath'] ?? null,

                    'proofUrl' => $sharedRow['proofUrl'] ?? null,

                    'highlightsByLocale' => $highlightsByLocale,

                    'isPrimary' => $isPrimary,

                ],

                count($migrated),

                $activeLocales,

                $defaultLocale,

            );

            if ($entry !== null) {

                $migrated[] = $entry;

            }

        }



        return $migrated;

    }



    /**

     * @brief Re-normalize persisted certification rows while keeping valid entries only.

     *

     * @param list<array<string, mixed>> $rows Stored rows.

     * @return list<array<string, mixed>>

     * @date 2026-06-11

     * @author Stephane H.

     */

    public static function sanitizePersistedEntries(array $rows): array

    {

        $activeLocales = self::collectLocalesFromRows($rows);

        if ($activeLocales === []) {

            $activeLocales = ['fr'];

        }



        $defaultLocale = in_array('fr', $activeLocales, true) ? 'fr' : $activeLocales[0];



        $sanitized = [];

        $sortOrder = 0;

        foreach ($rows as $row) {

            if (!is_array($row)) {

                continue;

            }



            $entry = self::normalizeEntry($row, $sortOrder, $activeLocales, $defaultLocale);

            if ($entry === null) {

                continue;

            }



            $sanitized[] = $entry;

            ++$sortOrder;

        }



        return $sanitized;

    }



    /**

     * @brief Validate stored relative proof PDF path for certification custom uploads.

     *

     * @param mixed $value Raw path from JSON or hidden field.

     * @return string|null Normalized path or null when absent/invalid.

     * @date 2026-05-31

     * @author Stephane H.

     */

    public static function normalizeStoredProofPdfPath(mixed $value): ?string

    {

        if (!is_string($value)) {

            return null;

        }



        $trimmed = trim($value);

        if ($trimmed === '' || !str_starts_with($trimmed, self::CERTIFICATION_PROOF_PDF_PATH_PREFIX)) {

            return null;

        }



        if (str_contains($trimmed, '..') || !str_ends_with(strtolower($trimmed), '.pdf')) {

            return null;

        }



        return $trimmed;

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

        return \App\Service\Uuid\DeterministicUuidFactory::generate('cv-certification', $seed);

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

     * @brief Normalize localized text maps from POST row.

     *

     * @param array<string, mixed> $row Raw row.

     * @param string $mapKey Canonical map key.

     * @param string $legacyKey Legacy flat key.

     * @param list<string> $activeLocales Site active locale codes.

     * @param int $maxLength Maximum string length.

     * @param bool $allowEmpty Whether empty strings are kept in the map.

     * @return array<string, string>

     * @date 2026-06-11

     * @author Stephane H.

     */

    private static function normalizeTextByLocale(

        array $row,

        string $mapKey,

        string $legacyKey,

        array $activeLocales,

        int $maxLength,

        bool $allowEmpty = false,

    ): array {

        $rawMap = $row[$mapKey] ?? null;

        $result = [];

        if (is_array($rawMap)) {

            foreach ($activeLocales as $locale) {

                $value = is_string($rawMap[$locale] ?? null) ? strip_tags(trim($rawMap[$locale])) : '';

                if ($value === '') {

                    if ($allowEmpty) {

                        $result[$locale] = '';

                    }



                    continue;

                }



                if (mb_strlen($value) > $maxLength) {

                    $value = mb_substr($value, 0, $maxLength);

                }



                $result[$locale] = $value;

            }



            return $result;

        }



        $legacyValue = is_string($row[$legacyKey] ?? null) ? strip_tags(trim($row[$legacyKey])) : '';

        if ($legacyValue !== '' && $activeLocales !== []) {

            if (mb_strlen($legacyValue) > $maxLength) {

                $legacyValue = mb_substr($legacyValue, 0, $maxLength);

            }



            $result[$activeLocales[0]] = $legacyValue;

        }



        return $result;

    }



    /**

     * @brief Normalize localized highlights maps from POST row.

     *

     * @param array<string, mixed> $row Raw row.

     * @param list<string> $activeLocales Site active locale codes.

     * @return array<string, list<string>>|null Null when invalid.

     * @date 2026-06-11

     * @author Stephane H.

     */

    private static function normalizeHighlightsByLocale(array $row, array $activeLocales): ?array

    {

        $rawMap = $row['highlightsByLocale'] ?? null;

        if (is_array($rawMap)) {

            $result = [];

            foreach ($activeLocales as $locale) {

                $normalized = self::normalizeHighlights($rawMap[$locale] ?? null);

                if ($normalized === null) {

                    return null;

                }



                if ($normalized !== []) {

                    $result[$locale] = $normalized;

                }

            }



            return $result;

        }



        $legacyHighlights = self::normalizeHighlights($row['highlights'] ?? null);

        if ($legacyHighlights === null) {

            return null;

        }



        if ($legacyHighlights === [] || $activeLocales === []) {

            return [];

        }



        return [$activeLocales[0] => $legacyHighlights];

    }



    /**

     * @brief Collect locale codes present in stored certification rows.

     *

     * @param list<array<string, mixed>> $rows Stored rows.

     * @return list<string>

     * @date 2026-06-11

     * @author Stephane H.

     */

    private static function collectLocalesFromRows(array $rows): array

    {

        $locales = [];

        $mapKeys = ['titleByLocale', 'providerNameByLocale', 'locationByLocale', 'highlightsByLocale'];

        foreach ($rows as $row) {

            if (!is_array($row)) {

                continue;

            }



            foreach ($mapKeys as $mapKey) {

                $map = $row[$mapKey] ?? null;

                if (!is_array($map)) {

                    continue;

                }



                foreach (array_keys($map) as $locale) {

                    if (is_string($locale) && $locale !== '' && !in_array($locale, $locales, true)) {

                        $locales[] = $locale;

                    }

                }

            }

        }



        return $locales;

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


