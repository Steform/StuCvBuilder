<?php

declare(strict_types=1);

namespace App\Cv;

use App\Service\Cv\CertificationContract;

/**
 * @brief Whitelist for per-company Certification section override JSON.
 */
final class CompanyCvCertificationOverrideScope
{
    /**
     * @var list<string>
     */
    public const PERSISTED_TOP_LEVEL_KEYS = [
        CertificationContract::KEY_ENTRIES,
    ];

    /**
     * @brief Extract Certification-related keys from a full CV profile payload.
     *
     * @param array<string, mixed> $profilePayload Global CV profile content JSON.
     * @return array<string, mixed>
     * @date 2026-06-11
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

        if ($slice === [] && array_key_exists(CertificationContract::KEY_ENTRIES_BY_LOCALE, $profilePayload)) {
            $slice[CertificationContract::KEY_ENTRIES_BY_LOCALE] = $profilePayload[CertificationContract::KEY_ENTRIES_BY_LOCALE];
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
     * @date 2026-06-11
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

        $entries = $sanitized[CertificationContract::KEY_ENTRIES] ?? null;
        if (!is_array($entries)) {
            return $sanitized;
        }

        $sanitized[CertificationContract::KEY_ENTRIES] = CertificationContract::sanitizePersistedEntries(array_values($entries));

        return $sanitized;
    }

    /**
     * @brief Merge Certification override keys into a resolved CV payload.
     *
     * @param array<string, mixed> $basePayload Default merged CV payload.
     * @param array<string, mixed> $overridePayload Company Certification override slice.
     * @return array<string, mixed>
     * @date 2026-06-11
     * @author Stephane H.
     */
    public static function mergeIntoPayload(array $basePayload, array $overridePayload): array
    {
        $sanitized = self::sanitizeForPersistence($overridePayload);
        foreach ($sanitized as $key => $value) {
            $basePayload[$key] = $value;
        }

        unset($basePayload[CertificationContract::KEY_ENTRIES_BY_LOCALE]);

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

    /**
     * @brief Migrate legacy per-locale certification map to canonical entries list.
     *
     * @param array<string, mixed> $payload Raw override JSON.
     * @param list<string> $activeLocales Site active locale codes.
     * @param string $defaultLocale Site default locale.
     * @return array<string, mixed>
     * @date 2026-06-11
     * @author Stephane H.
     */
    private static function migrateLegacyPayload(
        array $payload,
        array $activeLocales,
        string $defaultLocale,
    ): array {
        if (array_key_exists(CertificationContract::KEY_ENTRIES, $payload)) {
            return $payload;
        }

        $legacy = $payload[CertificationContract::KEY_ENTRIES_BY_LOCALE] ?? null;
        if (!is_array($legacy)) {
            return $payload;
        }

        $payload[CertificationContract::KEY_ENTRIES] = CertificationContract::migrateLegacyEntriesByLocale(
            $legacy,
            $activeLocales,
            $defaultLocale,
        );
        unset($payload[CertificationContract::KEY_ENTRIES_BY_LOCALE]);

        return $payload;
    }
}
