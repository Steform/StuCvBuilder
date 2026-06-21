<?php

declare(strict_types=1);

namespace App\Cv;

use App\Service\Cv\InterestsContract;

/**
 * @brief Whitelist for per-company Interests section override JSON.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
final class CompanyCvInterestsOverrideScope
{
    /** @var list<string> */
    public const PERSISTED_TOP_LEVEL_KEYS = [
        InterestsContract::KEY_ENTRIES,
        InterestsContract::KEY_COLUMNS_PER_ROW,
    ];

    /**
     * @brief Extract Interests-related keys from a full CV profile payload.
     *
     * @param array<string, mixed> $profilePayload Global CV profile content JSON.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function extractFromProfilePayload(array $profilePayload): array
    {
        $slice = [];
        foreach (self::PERSISTED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $profilePayload)) {
                continue;
            }

            $slice[$key] = $profilePayload[$key];
        }

        if ($slice === [] && array_key_exists(InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE, $profilePayload)) {
            $slice[InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE] = $profilePayload[InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE];
        }

        return self::sanitizeForPersistence($slice);
    }

    /**
     * @brief Sanitize override payload before persistence.
     *
     * @param array<string, mixed> $payload Raw override JSON.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function sanitizeForPersistence(
        array $payload,
        array $activeLocales = ['fr'],
        string $defaultLocale = 'fr',
    ): array {
        $payload = self::migrateLegacyPayload($payload, $activeLocales, $defaultLocale);

        $sanitized = [];
        foreach (self::PERSISTED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $sanitized[$key] = $payload[$key];
        }

        $entries = $sanitized[InterestsContract::KEY_ENTRIES] ?? null;
        if (!is_array($entries)) {
            return $sanitized;
        }

        $sanitized[InterestsContract::KEY_ENTRIES] = InterestsContract::sanitizePersistedEntries(array_values($entries));

        if (array_key_exists(InterestsContract::KEY_COLUMNS_PER_ROW, $sanitized)) {
            $sanitized[InterestsContract::KEY_COLUMNS_PER_ROW] = InterestsContract::normalizeColumnsPerRow(
                $sanitized[InterestsContract::KEY_COLUMNS_PER_ROW]
            );
        }

        return $sanitized;
    }

    /**
     * @brief Merge Interests override keys into a resolved CV payload.
     *
     * @param array<string, mixed> $basePayload Default merged CV payload.
     * @param array<string, mixed> $overridePayload Company Interests override slice.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function mergeIntoPayload(array $basePayload, array $overridePayload): array
    {
        $sanitized = self::sanitizeForPersistence($overridePayload);
        foreach ($sanitized as $key => $value) {
            $basePayload[$key] = $value;
        }

        unset($basePayload[InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE]);

        return $basePayload;
    }

    /**
     * @brief Decode stored override JSON to an array.
     *
     * @param string $json Stored JSON string.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @brief Migrate legacy per-locale interest map to canonical entries list.
     *
     * @param array<string, mixed> $payload Raw override JSON.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-09
     * @author Stephane H.
     */
    private static function migrateLegacyPayload(
        array $payload,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        if (array_key_exists(InterestsContract::KEY_ENTRIES, $payload)) {
            return $payload;
        }

        $legacy = $payload[InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($legacy)) {
            return $payload;
        }

        $payload[InterestsContract::KEY_ENTRIES] = InterestsContract::migrateLegacyEntriesByLocale(
            $legacy,
            $activeLocales,
            $defaultLocale,
        );
        unset($payload[InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE]);

        return $payload;
    }
}
