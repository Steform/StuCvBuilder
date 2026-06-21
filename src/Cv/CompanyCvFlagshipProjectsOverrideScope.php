<?php

declare(strict_types=1);

namespace App\Cv;

use App\Service\Cv\FlagshipProjectsContract;

/**
 * @brief Whitelist for per-company flagship projects section override JSON.
 */
final class CompanyCvFlagshipProjectsOverrideScope
{
    /**
     * @var list<string>
     */
    public const PERSISTED_TOP_LEVEL_KEYS = [
        FlagshipProjectsContract::KEY_SECTION_ENABLED,
        FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE,
    ];

    /**
     * @brief Extract flagship projects keys from a full CV profile payload.
     *
     * @param array<string, mixed> $profilePayload Global CV profile content JSON.
     * @return array<string, mixed>
     * @date 2026-06-01
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

        return $slice;
    }

    /**
     * @brief Sanitize override payload before persistence.
     *
     * @param array<string, mixed> $payload Raw override JSON.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function sanitizeForPersistence(array $payload, string $defaultLocale): array
    {
        $sanitized = [];
        foreach (self::PERSISTED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $sanitized[$key] = $payload[$key];
        }

        if (array_key_exists(FlagshipProjectsContract::KEY_SECTION_ENABLED, $sanitized)) {
            $sanitized[FlagshipProjectsContract::KEY_SECTION_ENABLED] = FlagshipProjectsContract::isSectionEnabledFromPayload([
                FlagshipProjectsContract::KEY_SECTION_ENABLED => $sanitized[FlagshipProjectsContract::KEY_SECTION_ENABLED],
            ]);
        }

        $map = $sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (is_array($map)) {
            $normalized = FlagshipProjectsContract::normalizeEntriesByLocale($map, $defaultLocale);
            if ($normalized === null) {
                unset($sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]);
            } else {
                $sanitized[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE] = $normalized;
            }
        }

        return $sanitized;
    }

    /**
     * @brief Merge flagship projects override keys into a resolved CV payload.
     *
     * @param array<string, mixed> $basePayload Default merged CV payload.
     * @param array<string, mixed> $overridePayload Company override slice.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function mergeIntoPayload(array $basePayload, array $overridePayload, string $defaultLocale): array
    {
        $sanitized = self::sanitizeForPersistence($overridePayload, $defaultLocale);
        foreach ($sanitized as $key => $value) {
            $basePayload[$key] = $value;
        }

        return $basePayload;
    }

    /**
     * @brief Decode stored override JSON to an array.
     *
     * @param string $json Stored JSON string.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
