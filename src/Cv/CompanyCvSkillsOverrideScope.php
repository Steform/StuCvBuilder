<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Whitelist for per-company Skills section override JSON (subset of global CV profile).
 */
final class CompanyCvSkillsOverrideScope
{
    /**
     * @var list<string>
     */
    public const PERSISTED_TOP_LEVEL_KEYS = [
        SkillsTreeContract::KEY,
    ];

    /**
     * @brief Extract Skills-related keys from a full CV profile payload.
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
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function sanitizeForPersistence(array $payload, array $activeLocales, string $defaultLocale): array
    {
        $sanitized = [];
        foreach (self::PERSISTED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $sanitized[$key] = $payload[$key];
        }

        $catalogRaw = $sanitized[SkillsTreeContract::KEY] ?? null;
        if (!is_array($catalogRaw)) {
            return $sanitized;
        }

        $normalized = SkillsTreeContract::normalizeCatalog($catalogRaw, $activeLocales, $defaultLocale);
        if ($normalized === null) {
            unset($sanitized[SkillsTreeContract::KEY]);

            return $sanitized;
        }

        $sanitized[SkillsTreeContract::KEY] = $normalized;

        return $sanitized;
    }

    /**
     * @brief Merge Skills override keys into a resolved CV payload.
     *
     * @param array<string, mixed> $basePayload Default merged CV payload.
     * @param array<string, mixed> $overridePayload Company Skills override slice.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function mergeIntoPayload(
        array $basePayload,
        array $overridePayload,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        $sanitized = self::sanitizeForPersistence($overridePayload, $activeLocales, $defaultLocale);
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
