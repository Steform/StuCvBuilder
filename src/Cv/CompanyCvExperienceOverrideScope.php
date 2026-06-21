<?php

declare(strict_types=1);

namespace App\Cv;

use App\Service\Cv\ExperienceContract;

/**
 * @brief Whitelist for per-company Experience section override JSON (subset of global CV profile).
 */
final class CompanyCvExperienceOverrideScope
{
    /**
     * @var list<string>
     */
    public const PERSISTED_TOP_LEVEL_KEYS = [
        ExperienceContract::KEY_ENTRIES_BY_LOCALE,
    ];

    /**
     * @brief Extract Experience-related keys from a full CV profile payload.
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

        return self::sanitizeForPersistence($slice);
    }

    /**
     * @brief Sanitize override payload before persistence.
     *
     * @param array<string, mixed> $payload Raw override JSON.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function sanitizeForPersistence(array $payload): array
    {
        $sanitized = [];
        foreach (self::PERSISTED_TOP_LEVEL_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $sanitized[$key] = $payload[$key];
        }

        $map = $sanitized[ExperienceContract::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($map)) {
            return $sanitized;
        }

        $normalizedMap = ExperienceContract::normalizeEntriesByLocale($map);
        if ($normalizedMap === null) {
            unset($sanitized[ExperienceContract::KEY_ENTRIES_BY_LOCALE]);

            return $sanitized;
        }

        $sanitized[ExperienceContract::KEY_ENTRIES_BY_LOCALE] = $normalizedMap;

        return $sanitized;
    }

    /**
     * @brief Merge Experience override keys into a resolved CV payload.
     *
     * @param array<string, mixed> $basePayload Default merged CV payload.
     * @param array<string, mixed> $overridePayload Company Experience override slice.
     * @return array<string, mixed>
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function mergeIntoPayload(array $basePayload, array $overridePayload): array
    {
        $sanitized = self::sanitizeForPersistence($overridePayload);
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
