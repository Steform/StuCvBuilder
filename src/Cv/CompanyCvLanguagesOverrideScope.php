<?php

declare(strict_types=1);

namespace App\Cv;

use App\Service\Cv\LanguagesContract;

/**
 * @brief Whitelist for per-company Languages section override JSON.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
final class CompanyCvLanguagesOverrideScope
{
    /** @var list<string> */
    public const PERSISTED_TOP_LEVEL_KEYS = [
        LanguagesContract::KEY_ENTRIES,
    ];

    /**
     * @brief Extract Languages-related keys from a full CV profile payload.
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

        return self::sanitizeForPersistence($slice);
    }

    /**
     * @brief Sanitize override payload before persistence.
     *
     * @param array<string, mixed> $payload Raw override JSON.
     * @return array<string, mixed>
     * @date 2026-06-09
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

        $entries = $sanitized[LanguagesContract::KEY_ENTRIES] ?? null;
        if (!is_array($entries)) {
            return $sanitized;
        }

        $sanitized[LanguagesContract::KEY_ENTRIES] = LanguagesContract::sanitizePersistedEntries(array_values($entries));

        return $sanitized;
    }

    /**
     * @brief Merge Languages override keys into a resolved CV payload.
     *
     * @param array<string, mixed> $basePayload Default merged CV payload.
     * @param array<string, mixed> $overridePayload Company Languages override slice.
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
}
