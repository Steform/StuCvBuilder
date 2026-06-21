<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @brief JSON keys, bounds, and parsing helpers for CV interest entries stored under CvProfile content_json.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
final class InterestsContract
{
    public const KEY_ENTRIES = 'interestEntries';

    public const KEY_COLUMNS_PER_ROW = 'interestsColumnsPerRow';

    /** @var string Legacy per-locale map migrated on read and stripped on persist. */
    public const LEGACY_KEY_ENTRIES_BY_LOCALE = 'interestEntriesByLocale';

    public const DEFAULT_COLUMNS_PER_ROW = 4;

    public const MIN_COLUMNS_PER_ROW = 2;

    public const MAX_COLUMNS_PER_ROW = 6;

    public const MAX_ENTRIES = 30;

    public const MAX_LABEL_LENGTH = 120;

    public const MAX_ICON_LENGTH = 80;

    public const ICON_TYPE_BOOTSTRAP = 'bootstrap';

    public const ICON_TYPE_IMAGE = 'image';

    public const INTEREST_ICON_PATH_PREFIX = 'images/cv/interests/custom/';

    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @brief Parse and normalize interest entries from admin POST.
     *
     * @param Request $request HTTP request with nested `interest_entries` array.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale used for required label validation.
     * @return list<array<string, mixed>>|null Null when validation fails.
     * @date 2026-06-09
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
     * @brief Parse raw interest rows from admin POST without final normalization.
     *
     * @param Request $request HTTP request.
     * @return list<array<string, mixed>>|null Null when structure is invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function parseRawEntriesFromRequest(Request $request): ?array
    {
        $raw = $request->request->all('interest_entries');
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
     * @brief Normalize a list of interest entries.
     *
     * @param list<array<string, mixed>> $rows Raw rows.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return list<array<string, mixed>>|null Null when any entry is invalid.
     * @date 2026-06-09
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
     * @brief Normalize one interest entry row with localized labels.
     *
     * @param array<string, mixed> $row Raw row.
     * @param int $sortOrder Display order index.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>|null Null when invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function normalizeEntry(
        array $row,
        int $sortOrder,
        array $activeLocales,
        string $defaultLocale,
    ): ?array {
        $labelByLocale = self::normalizeLabelByLocale($row, $activeLocales);
        $defaultLabel = $labelByLocale[$defaultLocale] ?? '';
        if ($defaultLabel === '') {
            return null;
        }

        $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
        if ($id === '' || !self::isValidUuid($id)) {
            $id = (string) Uuid::v4();
        }

        $iconFields = self::normalizeEntryIconFields($row);
        if ($iconFields === null) {
            return null;
        }

        return [
            'id' => $id,
            'iconType' => $iconFields['iconType'],
            'icon' => $iconFields['icon'],
            'iconPath' => $iconFields['iconPath'],
            'labelByLocale' => $labelByLocale,
            'sortOrder' => max(0, $sortOrder),
        ];
    }

    /**
     * @brief Normalize icon fields for one interest entry row.
     *
     * @param array<string, mixed> $row Raw row.
     * @return array{iconType: string, icon: string, iconPath: string|null}|null Null when invalid.
     * @date 2026-06-10
     * @author Stephane H.
     */
    public static function normalizeEntryIconFields(array $row): ?array
    {
        $iconType = is_string($row['iconType'] ?? null) ? trim($row['iconType']) : '';
        if ($iconType === '') {
            $iconType = self::normalizeIconPath($row['iconPath'] ?? null) !== null
                ? self::ICON_TYPE_IMAGE
                : self::ICON_TYPE_BOOTSTRAP;
        }

        if ($iconType === self::ICON_TYPE_IMAGE) {
            $iconPath = self::normalizeIconPath($row['iconPath'] ?? null);
            if ($iconPath === null) {
                return null;
            }

            return [
                'iconType' => self::ICON_TYPE_IMAGE,
                'icon' => '',
                'iconPath' => $iconPath,
            ];
        }

        $icon = is_string($row['icon'] ?? null) ? trim($row['icon']) : '';
        if ($icon !== '' && (strlen($icon) > self::MAX_ICON_LENGTH || !self::isValidBootstrapIconClass($icon))) {
            $icon = '';
        }

        return [
            'iconType' => self::ICON_TYPE_BOOTSTRAP,
            'icon' => $icon,
            'iconPath' => null,
        ];
    }

    /**
     * @brief Normalize a relative custom interest icon path for persistence and safe file cleanup.
     *
     * @param mixed $raw Relative icon path.
     * @return string|null Normalized path when valid, null otherwise.
     * @date 2026-06-10
     * @author Stephane H.
     */
    public static function normalizeIconPath(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = str_replace('\\', '/', trim($raw));
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return null;
        }

        $prefix = self::INTEREST_ICON_PATH_PREFIX;
        if (!str_starts_with($trimmed, $prefix)) {
            return null;
        }

        if (!preg_match('/^images\/cv\/interests\/custom\/interest-[a-z0-9-]+\.(webp|svg)$/i', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @brief Collect normalized custom icon paths from stored interest entries.
     *
     * @param list<array<string, mixed>> $entries Stored entries.
     * @return list<string>
     * @date 2026-06-10
     * @author Stephane H.
     */
    public static function collectCustomIconPaths(array $entries): array
    {
        $paths = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['iconType'] ?? '') !== self::ICON_TYPE_IMAGE) {
                continue;
            }

            $iconPath = self::normalizeIconPath($entry['iconPath'] ?? null);
            if ($iconPath !== null) {
                $paths[] = $iconPath;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @brief Read interest entries from decoded CvProfile payload (with legacy migration).
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
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

        $legacy = $payload[self::LEGACY_KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($legacy)) {
            return [];
        }

        return self::migrateLegacyEntriesByLocale($legacy, $activeLocales, $defaultLocale);
    }

    /**
     * @brief Whether persisted interest entries exist in payload.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function hasPersistedEntries(array $payload): bool
    {
        if (array_key_exists(self::KEY_ENTRIES, $payload) && is_array($payload[self::KEY_ENTRIES])) {
            return true;
        }

        return array_key_exists(self::LEGACY_KEY_ENTRIES_BY_LOCALE, $payload)
            && is_array($payload[self::LEGACY_KEY_ENTRIES_BY_LOCALE]);
    }

    /**
     * @brief Migrate legacy per-locale lists into canonical entries with labelByLocale.
     *
     * @param array<string, mixed> $legacyByLocale Legacy map locale => rows.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function migrateLegacyEntriesByLocale(
        array $legacyByLocale,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $maxCount = 0;
        foreach ($activeLocales as $locale) {
            $rows = $legacyByLocale[$locale] ?? null;
            if (is_array($rows)) {
                $maxCount = max($maxCount, count($rows));
            }
        }

        $migrated = [];
        for ($index = 0; $index < $maxCount; ++$index) {
            $labelByLocale = [];
            $id = null;
            $icon = '';
            foreach ($activeLocales as $locale) {
                $rows = $legacyByLocale[$locale] ?? null;
                if (!is_array($rows) || !isset($rows[$index]) || !is_array($rows[$index])) {
                    continue;
                }

                $row = $rows[$index];
                $candidateId = is_string($row['id'] ?? null) ? trim($row['id']) : '';
                if ($id === null && $candidateId !== '' && self::isValidUuid($candidateId)) {
                    $id = $candidateId;
                }

                $candidateIcon = is_string($row['icon'] ?? null) ? trim($row['icon']) : '';
                if ($icon === '' && $candidateIcon !== '') {
                    $icon = $candidateIcon;
                }

                $label = is_string($row['label'] ?? null) ? strip_tags(trim($row['label'])) : '';
                if ($label !== '' && strlen($label) <= self::MAX_LABEL_LENGTH) {
                    $labelByLocale[$locale] = $label;
                }
            }

            if ($labelByLocale === []) {
                continue;
            }

            $entry = self::normalizeEntry(
                [
                    'id' => $id ?? (string) Uuid::v4(),
                    'icon' => $icon,
                    'labelByLocale' => $labelByLocale,
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
     * @brief Normalize localized labels from POST row.
     *
     * @param array<string, mixed> $row Raw row.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array<string, string>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private static function normalizeLabelByLocale(array $row, array $activeLocales): array
    {
        $rawMap = $row['labelByLocale'] ?? null;
        $labelByLocale = [];
        if (is_array($rawMap)) {
            foreach ($activeLocales as $locale) {
                $label = is_string($rawMap[$locale] ?? null) ? strip_tags(trim($rawMap[$locale])) : '';
                if ($label !== '' && strlen($label) <= self::MAX_LABEL_LENGTH) {
                    $labelByLocale[$locale] = $label;
                }
            }

            return $labelByLocale;
        }

        $legacyLabel = is_string($row['label'] ?? null) ? strip_tags(trim($row['label'])) : '';
        if ($legacyLabel !== '' && strlen($legacyLabel) <= self::MAX_LABEL_LENGTH && $activeLocales !== []) {
            $labelByLocale[$activeLocales[0]] = $legacyLabel;
        }

        return $labelByLocale;
    }

    /**
     * @brief Re-normalize persisted interest rows while keeping valid entries only.
     *
     * @param list<array<string, mixed>> $rows Stored rows.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
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
     * @brief Collect locale codes present in stored interest rows.
     *
     * @param list<array<string, mixed>> $rows Stored rows.
     * @return list<string>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private static function collectLocalesFromRows(array $rows): array
    {
        $locales = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $labelByLocale = $row['labelByLocale'] ?? null;
            if (!is_array($labelByLocale)) {
                continue;
            }

            foreach (array_keys($labelByLocale) as $locale) {
                if (is_string($locale) && $locale !== '' && !in_array($locale, $locales, true)) {
                    $locales[] = $locale;
                }
            }
        }

        return $locales;
    }

    /**
     * @brief Validate UUID v4 format.
     *
     * @param string $value Candidate UUID.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function isValidUuid(string $value): bool
    {
        return preg_match(self::UUID_V4_PATTERN, $value) === 1;
    }

    /**
     * @brief Validate Bootstrap Icons class token (bi-*).
     *
     * @param string $icon Icon class string.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function isValidBootstrapIconClass(string $icon): bool
    {
        return preg_match('/^bi-[a-z0-9-]+$/', $icon) === 1;
    }

    /**
     * @brief Normalize the number of interest tiles displayed per row on large screens.
     *
     * @param mixed $raw Raw stored or submitted value.
     * @return int Clamped integer between {@see MIN_COLUMNS_PER_ROW} and {@see MAX_COLUMNS_PER_ROW}.
     * @date 2026-06-11
     * @author Stephane H.
     */
    public static function normalizeColumnsPerRow(mixed $raw): int
    {
        if (is_string($raw) && ctype_digit(trim($raw))) {
            $raw = (int) trim($raw);
        }

        if (!is_int($raw) && !is_float($raw)) {
            return self::DEFAULT_COLUMNS_PER_ROW;
        }

        $value = (int) round($raw);

        return max(self::MIN_COLUMNS_PER_ROW, min(self::MAX_COLUMNS_PER_ROW, $value));
    }

    /**
     * @brief Read the configured interests grid density from a decoded payload.
     *
     * @param array<string, mixed> $payload Decoded profile or override JSON.
     * @return int Normalized columns-per-row value for Bootstrap `row-cols-lg-*`.
     * @date 2026-06-11
     * @author Stephane H.
     */
    public static function columnsPerRowFromPayload(array $payload): int
    {
        if (!array_key_exists(self::KEY_COLUMNS_PER_ROW, $payload)) {
            return self::DEFAULT_COLUMNS_PER_ROW;
        }

        return self::normalizeColumnsPerRow($payload[self::KEY_COLUMNS_PER_ROW]);
    }
}
