<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * @brief JSON keys, bounds, and parsing helpers for CV professional web profile links stored under CvProfile content_json.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
final class WebProfilesContract
{
    public const KEY_ENTRIES = 'webProfileEntries';

    public const MAX_ENTRIES = 15;

    public const MAX_URL_LENGTH = 500;

    public const MAX_LABEL_LENGTH = 80;

    /** @var list<string> */
    public const PLATFORM_CODES = [
        'github',
        'linkedin',
        'gitlab',
        'bitbucket',
        'stackoverflow',
        'mastodon',
        'medium',
        'website',
        'other',
    ];

    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @brief Parse and normalize web profile entries from admin POST.
     *
     * @param Request $request HTTP request with nested `web_profile_entries` array.
     * @return list<array<string, mixed>>|null Null when validation fails.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function parseEntriesFromRequest(Request $request): ?array
    {
        $raw = self::parseRawEntriesFromRequest($request);
        if ($raw === null) {
            return null;
        }

        return self::normalizeEntries($raw);
    }

    /**
     * @brief Parse raw web profile rows from admin POST without final normalization.
     *
     * @param Request $request HTTP request.
     * @return list<array<string, mixed>>|null Null when structure is invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function parseRawEntriesFromRequest(Request $request): ?array
    {
        $raw = $request->request->all('web_profile_entries');
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
     * @brief Normalize a list of web profile entries.
     *
     * @param list<array<string, mixed>> $rows Raw rows.
     * @return list<array<string, mixed>>|null Null when any entry is invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function normalizeEntries(array $rows): ?array
    {
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

        return $normalized;
    }

    /**
     * @brief Normalize one web profile entry row.
     *
     * @param array<string, mixed> $row Raw row.
     * @param int $sortOrder Display order index.
     * @return array<string, mixed>|null Null when invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function normalizeEntry(array $row, int $sortOrder): ?array
    {
        $platform = is_string($row['platform'] ?? null) ? strtolower(trim($row['platform'])) : '';
        if (!in_array($platform, self::PLATFORM_CODES, true)) {
            return null;
        }

        $url = self::normalizeHttpUrl($row['url'] ?? null);
        if ($url === null) {
            return null;
        }

        $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';
        if ($id === '' || !self::isValidUuid($id)) {
            $id = (string) Uuid::v4();
        }

        $label = is_string($row['label'] ?? null) ? strip_tags(trim($row['label'])) : '';
        if (strlen($label) > self::MAX_LABEL_LENGTH) {
            $label = substr($label, 0, self::MAX_LABEL_LENGTH);
        }

        $visible = self::normalizeBool($row['visible'] ?? true);

        return [
            'id' => $id,
            'platform' => $platform,
            'url' => $url,
            'label' => $label,
            'visible' => $visible,
            'sortOrder' => max(0, $sortOrder),
        ];
    }

    /**
     * @brief Read web profile entries from decoded CvProfile payload.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function entriesFromStoredPayload(array $payload): array
    {
        $raw = $payload[self::KEY_ENTRIES] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $normalized = self::normalizeEntries(array_values($raw));

        return $normalized ?? [];
    }

    /**
     * @brief Whether persisted web profile entries exist in payload.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function hasPersistedEntries(array $payload): bool
    {
        return array_key_exists(self::KEY_ENTRIES, $payload)
            && is_array($payload[self::KEY_ENTRIES]);
    }

    /**
     * @brief Filter visible entries for public display.
     *
     * @param list<array<string, mixed>> $entries Normalized entries.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function filterVisible(array $entries): array
    {
        $filtered = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['visible'] ?? true) === true
        ));

        usort($filtered, static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

        return $filtered;
    }

    /**
     * @brief Normalize and validate an HTTP(S) URL.
     *
     * @param mixed $value Raw URL value.
     * @return string|null Sanitized URL or null when invalid.
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function normalizeHttpUrl(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $url = trim($value);
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host']) || !is_string($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        if ($host === '' || $host === 'localhost') {
            return null;
        }

        return $url;
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
     * @brief Normalize checkbox-like values to bool.
     *
     * @param mixed $value Raw value.
     * @return bool
     * @date 2026-06-09
     * @author Stephane H.
     */
    private static function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }
}
