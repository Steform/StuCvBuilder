<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @brief JSON keys, bounds, and parsing helpers for CV reference entries stored under CvProfile content_json.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
final class ReferencesContract
{
    public const KEY_SECTION_ENABLED = 'referencesSectionEnabled';

    public const KEY_ENTRIES_BY_LOCALE = 'referenceEntriesByLocale';

    public const MAX_ENTRIES_PER_LOCALE = 15;

    public const MAX_NAME_LENGTH = 120;

    public const MAX_ROLE_LENGTH = 200;

    public const MAX_ORGANIZATION_LENGTH = 200;

    public const MAX_RELATIONSHIP_LENGTH = 200;

    public const MAX_QUOTE_LENGTH = 500;

    public const MAX_EMAIL_LENGTH = 200;

    public const MAX_PHONE_LENGTH = 40;

    /** @var list<string> */
    public const CONTACT_MODES = [
        'on_request',
        'email',
        'phone',
    ];

    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @brief Parse section visibility from admin customization POST fields.
     *
     * @param Request $request HTTP request with `references_section_enabled`.
     * @return bool True when the section should be visible on the public CV.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function parseSectionEnabledFromRequest(Request $request): bool
    {
        $all = $request->request->all();
        if (!array_key_exists('references_section_enabled', $all)) {
            return false;
        }

        return self::normalizeEnabled($all['references_section_enabled']);
    }

    /**
     * @brief Parse and normalize reference entries from admin POST for all active locales.
     *
     * @param Request $request HTTP request with nested `reference_entries` array.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array<string, list<array<string, mixed>>>|null Null when validation fails.
     * @date 2026-06-09
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
     * @brief Parse raw reference rows from admin POST without final normalization.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array<string, list<array<string, mixed>>>|null Null when structure is invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function parseRawEntriesFromRequest(Request $request, array $activeLocales): ?array
    {
        $raw = $request->request->all('reference_entries');
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
     * @brief Normalize all locale entry lists.
     *
     * @param array<string, list<array<string, mixed>>> $rowsByLocale Raw rows keyed by locale.
     * @return array<string, list<array<string, mixed>>>|null Null when any entry is invalid.
     * @date 2026-06-09
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

        return $result;
    }

    /**
     * @brief Normalize one reference entry row.
     *
     * @param array<string, mixed> $row Raw row.
     * @param int $sortOrder Display order index.
     * @return array<string, mixed>|null Null when invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function normalizeEntry(array $row, int $sortOrder): ?array
    {
        $name = is_string($row['name'] ?? null) ? strip_tags(trim($row['name'])) : '';
        if ($name === '' || strlen($name) > self::MAX_NAME_LENGTH) {
            return null;
        }

        $role = self::normalizeOptionalString($row['role'] ?? null, self::MAX_ROLE_LENGTH);
        $organization = self::normalizeOptionalString($row['organization'] ?? null, self::MAX_ORGANIZATION_LENGTH);
        $relationship = self::normalizeOptionalString($row['relationship'] ?? null, self::MAX_RELATIONSHIP_LENGTH);
        $quote = self::normalizeOptionalString($row['quote'] ?? null, self::MAX_QUOTE_LENGTH);

        $contactMode = is_string($row['contactMode'] ?? null) ? strtolower(trim($row['contactMode'])) : 'on_request';
        if (!in_array($contactMode, self::CONTACT_MODES, true)) {
            $contactMode = 'on_request';
        }

        $email = self::normalizeOptionalString($row['email'] ?? null, self::MAX_EMAIL_LENGTH);
        $phone = self::normalizeOptionalString($row['phone'] ?? null, self::MAX_PHONE_LENGTH);

        if ($contactMode === 'email' && ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $contactMode = 'on_request';
            $email = null;
        }

        if ($contactMode === 'phone' && ($phone === null || $phone === '')) {
            $contactMode = 'on_request';
            $phone = null;
        }

        if ($contactMode === 'on_request') {
            $email = null;
            $phone = null;
        }

        $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
        if ($id === '' || !self::isValidUuid($id)) {
            $id = (string) Uuid::v4();
        }

        return [
            'id' => $id,
            'name' => $name,
            'role' => $role ?? '',
            'organization' => $organization ?? '',
            'relationship' => $relationship ?? '',
            'quote' => $quote ?? '',
            'contactMode' => $contactMode,
            'email' => $email,
            'phone' => $phone,
            'sortOrder' => max(0, $sortOrder),
        ];
    }

    /**
     * @brief Read reference entries map from decoded CvProfile payload.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @return array<string, list<array<string, mixed>>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function entriesByLocaleFromStoredPayload(array $payload): array
    {
        $raw = $payload[self::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $normalized = self::normalizeEntriesByLocale($raw);

        return $normalized ?? [];
    }

    /**
     * @brief Whether persisted reference entries exist in payload.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function hasPersistedMap(array $payload): bool
    {
        return array_key_exists(self::KEY_ENTRIES_BY_LOCALE, $payload)
            && is_array($payload[self::KEY_ENTRIES_BY_LOCALE]);
    }

    /**
     * @brief Resolve section enabled flag from payload (default false).
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function isSectionEnabledFromPayload(array $payload): bool
    {
        if (!array_key_exists(self::KEY_SECTION_ENABLED, $payload)) {
            return false;
        }

        return self::normalizeEnabled($payload[self::KEY_SECTION_ENABLED]);
    }

    /**
     * @brief Normalize enabled flag from stored or request value.
     *
     * @param mixed $value Raw value.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function normalizeEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::normalizeEnabled($item)) {
                    return true;
                }
            }

            return false;
        }

        return false;
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
     * @brief Normalize optional stripped string with max length.
     *
     * @param mixed $value Raw value.
     * @param int $maxLength Maximum length.
     * @return string|null Null when empty after trim.
     * @date 2026-06-09
     * @author Stephane H.
     */
    private static function normalizeOptionalString(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = strip_tags(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > $maxLength) {
            $normalized = substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }
}
