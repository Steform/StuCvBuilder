<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * JSON keys, bounds, and parsing helpers for CV professional experience entries stored under CvProfile content_json.
 */
final class ExperienceContract
{
    public const KEY_ENTRIES_BY_LOCALE = 'experienceEntriesByLocale';

    public const MAX_ENTRIES_PER_LOCALE = 30;

    public const MAX_HIGHLIGHTS_PER_ENTRY = 20;

    public const MAX_TITLE_LENGTH = 200;

    public const MAX_COMPANY_NAME_LENGTH = 200;

    public const MAX_HIGHLIGHT_LENGTH = 500;

    public const MAX_WEBSITE_URL_LENGTH = 500;

    public const MAX_LOCATION_LENGTH = 200;

    public const EXPERIENCE_LOGO_PATH_PREFIX = 'images/cv/experience/custom/';

    private const YEAR_MONTH_PATTERN = '/^\d{4}-\d{2}$/';

    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @var list<string> Fields shared across locales for the same entry id (not title/highlights). */
    private const SHARED_ENTRY_FIELD_KEYS = [
        'sortOrder',
        'startDate',
        'endDate',
        'isCurrent',
        'companyName',
        'companyLogoPath',
        'companyWebsiteUrl',
        'location',
        'hideCompanyName',
        'isPrimary',
    ];

    /**
     * @brief Parse and normalize experience entries from admin POST for all active locales.
     *
     * @param Request $request HTTP request with nested `experience_entries` array.
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
     * @brief Parse raw experience rows from admin POST without final normalization (for logo merge first).
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array<string, list<array<string, mixed>>>|null Null when structure is invalid.
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function parseRawEntriesFromRequest(Request $request, array $activeLocales): ?array
    {
        $raw = $request->request->all('experience_entries');
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
        $status = self::normalizeEntriesByLocaleWithStatus($rowsByLocale);

        return $status['entries'];
    }

    /**
     * @brief Normalize locale rows, sort chronologically by dates, and detect overlapping periods.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Raw rows keyed by locale.
     * @return array{
     *     entries: array<string, list<array<string, mixed>>>|null,
     *     error: 'invalid'|null
     * }
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function normalizeEntriesByLocaleWithStatus(array $rowsByLocale): array
    {
        $built = self::buildNormalizedEntriesByLocale($rowsByLocale);
        if ($built === null) {
            return ['entries' => null, 'error' => 'invalid'];
        }

        return ['entries' => $built, 'error' => null];
    }

    /**
     * @brief Normalize, sync shared fields, sort by dates, without overlap validation.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Raw rows keyed by locale.
     * @return array<string, list<array<string, mixed>>>|null Null when any entry is invalid.
     * @date 2026-06-03
     * @author Stephane H.
     */
    private static function buildNormalizedEntriesByLocale(array $rowsByLocale): ?array
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

        $result = self::syncSharedEntryFieldsAcrossLocales($result);
        $result = self::syncIsPrimaryAcrossLocales($result);

        return self::sortEntriesByManualOrderAcrossLocales($result);
    }

    /**
     * @brief Reorder every locale list using the canonical locale manual sortOrder.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Normalized entries keyed by locale.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-04
     * @author Stephane H.
     */
    public static function sortEntriesByManualOrderAcrossLocales(array $entriesByLocale): array
    {
        $canonicalKey = self::resolveCanonicalLocaleKey($entriesByLocale);
        $canonical = $entriesByLocale[$canonicalKey] ?? [];
        if (!is_array($canonical) || $canonical === []) {
            return $entriesByLocale;
        }

        $sortedCanonical = $canonical;
        usort(
            $sortedCanonical,
            static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0)
        );

        return self::reorderEntriesAcrossLocalesByIdOrder($entriesByLocale, $sortedCanonical);
    }

    /**
     * @brief Whether any two experience periods overlap on the canonical locale timeline.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Normalized entries keyed by locale.
     * @return bool True when at least one pair overlaps.
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function entriesByLocaleHaveDateOverlaps(array $entriesByLocale): bool
    {
        $canonicalKey = self::resolveCanonicalLocaleKey($entriesByLocale);
        $entries = $entriesByLocale[$canonicalKey] ?? [];
        if (!is_array($entries)) {
            return false;
        }

        $count = count($entries);
        for ($i = 0; $i < $count; ++$i) {
            if (!is_array($entries[$i])) {
                continue;
            }

            for ($j = $i + 1; $j < $count; ++$j) {
                if (!is_array($entries[$j])) {
                    continue;
                }

                if (self::entriesOverlap($entries[$i], $entries[$j])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @brief Compare two normalized entries for CV timeline order (most recent first).
     *
     * @param array<string, mixed> $a First entry.
     * @param array<string, mixed> $b Second entry.
     * @return int Comparator result for usort.
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function compareEntriesChronologically(array $a, array $b): int
    {
        $aCurrent = ($a['isCurrent'] ?? false) === true;
        $bCurrent = ($b['isCurrent'] ?? false) === true;
        if ($aCurrent !== $bCurrent) {
            return $bCurrent <=> $aCurrent;
        }

        $startCmp = self::yearMonthToOrdinal((string) ($b['startDate'] ?? ''))
            <=> self::yearMonthToOrdinal((string) ($a['startDate'] ?? ''));
        if ($startCmp !== 0) {
            return $startCmp;
        }

        $aEndOrdinal = $aCurrent
            ? PHP_INT_MAX
            : self::yearMonthToOrdinal((string) ($a['endDate'] ?? ''));
        $bEndOrdinal = $bCurrent
            ? PHP_INT_MAX
            : self::yearMonthToOrdinal((string) ($b['endDate'] ?? ''));
        $endCmp = $bEndOrdinal <=> $aEndOrdinal;
        if ($endCmp !== 0) {
            return $endCmp;
        }

        $aId = isset($a['id']) && is_string($a['id']) ? $a['id'] : '';
        $bId = isset($b['id']) && is_string($b['id']) ? $b['id'] : '';

        return strcmp($aId, $bId);
    }

    /**
     * @brief Detect inclusive month-range overlap between two experience entries.
     *
     * @param array<string, mixed> $a First entry with startDate, optional endDate, isCurrent.
     * @param array<string, mixed> $b Second entry.
     * @return bool True when periods share at least one month.
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function entriesOverlap(array $a, array $b): bool
    {
        $aStart = self::yearMonthToOrdinal((string) ($a['startDate'] ?? ''));
        $bStart = self::yearMonthToOrdinal((string) ($b['startDate'] ?? ''));
        $aIsCurrent = ($a['isCurrent'] ?? false) === true;
        $bIsCurrent = ($b['isCurrent'] ?? false) === true;
        $aEnd = $aIsCurrent ? PHP_INT_MAX : self::yearMonthToOrdinal((string) ($a['endDate'] ?? ''));
        $bEnd = $bIsCurrent ? PHP_INT_MAX : self::yearMonthToOrdinal((string) ($b['endDate'] ?? ''));

        if ($aStart === 0 || $bStart === 0) {
            return false;
        }

        if (!$aIsCurrent && $aEnd === 0) {
            return false;
        }

        if (!$bIsCurrent && $bEnd === 0) {
            return false;
        }

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }

    /**
     * @brief Reorder every locale list using the same chronological order (canonical locale dates).
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Normalized entries keyed by locale.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function sortEntriesChronologicallyAcrossLocales(array $entriesByLocale): array
    {
        $canonicalKey = self::resolveCanonicalLocaleKey($entriesByLocale);
        $canonical = $entriesByLocale[$canonicalKey] ?? [];
        if (!is_array($canonical) || $canonical === []) {
            return $entriesByLocale;
        }

        $sortedCanonical = self::sortEntriesChronologicallyForLocale($canonical);

        return self::reorderEntriesAcrossLocalesByIdOrder($entriesByLocale, $sortedCanonical);
    }

    /**
     * @brief Apply one canonical entry order to every locale list by shared entry id.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Normalized entries keyed by locale.
     * @param list<array<string, mixed>> $orderedCanonical Canonical locale rows in desired order.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-04
     * @author Stephane H.
     */
    private static function reorderEntriesAcrossLocalesByIdOrder(array $entriesByLocale, array $orderedCanonical): array
    {
        /** @var list<string> $orderById */
        $orderById = [];
        foreach ($orderedCanonical as $entry) {
            $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
            if ($entryId !== '') {
                $orderById[] = $entryId;
            }
        }

        /** @var array<string, array<string, array<string, mixed>>> $byIdPerLocale */
        $byIdPerLocale = [];
        foreach ($entriesByLocale as $locale => $entries) {
            if (!is_string($locale) || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId !== '') {
                    $byIdPerLocale[$locale][$entryId] = $entry;
                }
            }
        }

        $result = [];
        foreach ($entriesByLocale as $locale => $entries) {
            if (!is_string($locale) || !is_array($entries)) {
                continue;
            }

            $reordered = [];
            foreach ($orderById as $index => $entryId) {
                if (!isset($byIdPerLocale[$locale][$entryId])) {
                    continue;
                }

                $entry = $byIdPerLocale[$locale][$entryId];
                $entry['sortOrder'] = $index;
                $reordered[] = $entry;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId === '' || in_array($entryId, $orderById, true)) {
                    continue;
                }

                $entry['sortOrder'] = count($reordered);
                $reordered[] = $entry;
            }

            $result[$locale] = $reordered;
        }

        return $result;
    }

    /**
     * @brief Sort one locale entry list by start/end dates and reassign sortOrder indexes.
     *
     * @param list<array<string, mixed>> $entries Entries for a single locale.
     * @return list<array<string, mixed>>
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function sortEntriesChronologicallyForLocale(array $entries): array
    {
        $sorted = $entries;
        usort($sorted, [self::class, 'compareEntriesChronologically']);

        foreach ($sorted as $index => &$entry) {
            $entry['sortOrder'] = $index;
        }
        unset($entry);

        return $sorted;
    }

    /**
     * @brief Convert YYYY-MM to a monotonic month ordinal for comparisons.
     *
     * @param string $yearMonth ISO year-month value.
     * @return int Zero when invalid.
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function yearMonthToOrdinal(string $yearMonth): int
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 12) + (int) $matches[2];
    }

    /**
     * @brief Align structural fields for the same entry id across all locale lists.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed experience rows.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function syncSharedEntryFieldsAcrossLocales(array $entriesByLocale): array
    {
        $canonicalLocale = self::resolveCanonicalLocaleKey($entriesByLocale);
        /** @var array<string, array<string, array<string, mixed>>> $entriesById */
        $entriesById = [];

        foreach ($entriesByLocale as $locale => $entries) {
            if (!is_string($locale) || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId === '') {
                    continue;
                }

                $entriesById[$entryId][$locale] = $entry;
            }
        }

        /** @var array<string, array<string, mixed>> $sharedById */
        $sharedById = [];
        foreach ($entriesById as $entryId => $perLocale) {
            $canonical = $perLocale[$canonicalLocale] ?? reset($perLocale);
            if (!is_array($canonical)) {
                continue;
            }

            $shared = [];
            foreach (self::SHARED_ENTRY_FIELD_KEYS as $key) {
                if (array_key_exists($key, $canonical)) {
                    $shared[$key] = $canonical[$key];
                }
            }

            $sharedById[$entryId] = $shared;
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

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId !== '' && isset($sharedById[$entryId])) {
                    foreach (self::SHARED_ENTRY_FIELD_KEYS as $key) {
                        if (array_key_exists($key, $sharedById[$entryId])) {
                            $entry[$key] = $sharedById[$entryId][$key];
                        }
                    }
                }

                $localeEntries[] = $entry;
            }

            $synced[$locale] = $localeEntries;
        }

        return $synced;
    }

    /**
     * @brief Keep isPrimary aligned across locales for the same entry id.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed experience rows.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function syncIsPrimaryAcrossLocales(array $entriesByLocale): array
    {
        $isPrimaryByEntryId = [];
        foreach ($entriesByLocale as $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId === '') {
                    continue;
                }

                $isPrimaryByEntryId[$entryId] = ($isPrimaryByEntryId[$entryId] ?? true)
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

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId !== '') {
                    $entry['isPrimary'] = $isPrimaryByEntryId[$entryId] ?? true;
                }

                $localeEntries[] = $entry;
            }

            $synced[$locale] = $localeEntries;
        }

        return $synced;
    }

    /**
     * @brief Resolve locale used as canonical source when merging shared entry fields.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed rows.
     * @return string Locale code.
     * @date 2026-06-03
     * @author Stephane H.
     */
    private static function resolveCanonicalLocaleKey(array $entriesByLocale): string
    {
        if (isset($entriesByLocale['fr'])) {
            return 'fr';
        }

        $first = array_key_first($entriesByLocale);

        return is_string($first) && $first !== '' ? $first : 'fr';
    }

    /**
     * @brief Normalize a single experience entry from request or stored JSON.
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

        $logoPath = $resolvedLogoPath ?? self::normalizeStoredLogoPath($row['companyLogoPath'] ?? null);

        $companyNameRaw = self::normalizeText($row['companyName'] ?? null, self::MAX_COMPANY_NAME_LENGTH);
        if ($companyNameRaw === null) {
            return null;
        }

        $companyName = $companyNameRaw;
        if ($companyName === '' && $logoPath === null) {
            return null;
        }

        $websiteUrl = self::normalizeWebsiteUrl($row['companyWebsiteUrl'] ?? null);

        $location = self::normalizeOptionalLocation($row['location'] ?? null);

        $highlights = self::normalizeHighlights($row['highlights'] ?? null);
        if ($highlights === null) {
            return null;
        }

        $detailHtml = self::normalizeDetailHtml($row['detailHtml'] ?? null);

        return [
            'id' => $id,
            'sortOrder' => max(0, $sortOrder),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isCurrent' => $isCurrent,
            'title' => $title,
            'companyName' => $companyName,
            'companyWebsiteUrl' => $websiteUrl,
            'location' => $location,
            'companyLogoPath' => $logoPath,
            'hideCompanyName' => self::normalizeBool($row['hideCompanyName'] ?? false),
            'highlights' => $highlights,
            'detailHtml' => $detailHtml,
            'isPrimary' => self::resolveIsPrimaryFromRow($row),
        ];
    }

    /**
     * @brief Normalize sanitized rich-text detail HTML stored on an experience entry.
     *
     * @param mixed $value Raw HTML from admin POST (must be sanitized before persistence).
     * @return string Empty string when absent.
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function normalizeDetailHtml(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) > 100_000) {
            return mb_substr($trimmed, 0, 100_000);
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
     * @brief Validate stored relative logo path for experience custom uploads.
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
        if ($trimmed === '' || !str_starts_with($trimmed, self::EXPERIENCE_LOGO_PATH_PREFIX)) {
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

            $result[$locale] = $normalized;
        }

        return self::sortEntriesByManualOrderAcrossLocales($result);
    }

    /**
     * @brief Ensure each active locale has one row per shared entry id (restores missing translations).
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale-keyed rows.
     * @param list<string> $activeLocales Active site locale codes.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function alignEntriesAcrossActiveLocales(array $entriesByLocale, array $activeLocales): array
    {
        if ($activeLocales === []) {
            return $entriesByLocale;
        }

        /** @var array<string, array<string, mixed>> $templateById */
        $templateById = [];
        /** @var array<string, array<string, array<string, mixed>>> $byIdPerLocale */
        $byIdPerLocale = [];
        foreach ($entriesByLocale as $locale => $entries) {
            if (!is_string($locale) || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId === '' || !self::isValidUuid($entryId)) {
                    continue;
                }

                $byIdPerLocale[$locale][$entryId] = $entry;
                if (!isset($templateById[$entryId])) {
                    $templateById[$entryId] = $entry;
                }
            }
        }

        if ($templateById === []) {
            $aligned = [];
            foreach ($activeLocales as $locale) {
                $aligned[$locale] = [];
            }

            return $aligned;
        }

        $canonicalKey = self::resolveCanonicalLocaleKey($entriesByLocale);
        /** @var list<string> $orderById */
        $orderById = [];
        $canonical = $entriesByLocale[$canonicalKey] ?? [];
        if (is_array($canonical)) {
            foreach ($canonical as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
                if ($entryId !== '' && !in_array($entryId, $orderById, true)) {
                    $orderById[] = $entryId;
                }
            }
        }

        foreach (array_keys($templateById) as $entryId) {
            if (!in_array($entryId, $orderById, true)) {
                $orderById[] = $entryId;
            }
        }

        $aligned = [];
        foreach ($activeLocales as $locale) {
            $localeRows = [];
            foreach ($orderById as $index => $entryId) {
                if (isset($byIdPerLocale[$locale][$entryId])) {
                    $row = $byIdPerLocale[$locale][$entryId];
                } elseif (isset($templateById[$entryId])) {
                    $row = $templateById[$entryId];
                    $row['title'] = '';
                    $row['detailHtml'] = '';
                    $row['highlights'] = [];
                } else {
                    continue;
                }

                $row['id'] = $entryId;
                $row['sortOrder'] = $index;
                $localeRows[] = $row;
            }

            $aligned[$locale] = $localeRows;
        }

        return self::syncSharedEntryFieldsAcrossLocales(self::syncIsPrimaryAcrossLocales($aligned));
    }

    /**
     * @brief Whether persisted JSON already contains the experience map key (admin has saved at least once).
     *
     * @param array<string, mixed> $payload Decoded JSON.
     * @return bool
     * @date 2026-05-15
     * @author Stephane H.
     */
    public static function hasPersistedExperienceMap(array $payload): bool
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
        return \App\Service\Uuid\DeterministicUuidFactory::generate('cv-experience', $seed);
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
     * @brief Format YYYY-MM for display as YYYY/MM (month always shown, including January).
     *
     * @param string $yearMonth ISO year-month.
     * @return string Display label such as 2018/01.
     * @date 2026-06-03
     * @author Stephane H.
     */
    public static function formatYearMonthForDisplay(string $yearMonth): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $matches)) {
            return $yearMonth;
        }

        return sprintf('%s/%02d', $matches[1], (int) $matches[2]);
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
