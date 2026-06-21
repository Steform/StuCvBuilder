<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\AboutPresentationTypographyContract;
use App\Cv\AboutSectionPatternCustomizationContract;

/**
 * @brief JSON keys and helpers for About presentation rich HTML (per locale).
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
final class AboutPresentationContract
{
    public const KEY_HTML = 'aboutPresentationHtml';

    /** @var string Persisted map locale code => HTML body (sanitized on save). Legacy {@see self::KEY_HTML} is migrated at read time when this key is absent. */
    public const KEY_HTML_BY_LOCALE = 'aboutPresentationHtmlByLocale';

    /** @deprecated Stored only in legacy profiles; stripped on save. */
    public const KEY_LAYOUT_DESKTOP = 'aboutPresentationLayoutDesktop';

    /** @deprecated Stored only in legacy profiles; stripped on save. */
    public const KEY_LAYOUT_MOBILE = 'aboutPresentationLayoutMobile';

    /**
     * @brief Build unsanitized per-locale HTML map from stored payload (migrates legacy scalar when the map key is missing).
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @param list<string> $activeLocales Allowed locale codes in display order.
     * @param string $defaultLocale Site default locale for legacy copy.
     * @return array<string, string> One entry per active locale; values are raw strings from storage.
     * @date 2026-05-10
     * @author Stephane H.
     */
    public static function htmlByLocaleFromStoredPayload(array $payload, array $activeLocales, string $defaultLocale): array
    {
        $map = [];
        foreach ($activeLocales as $loc) {
            $map[$loc] = '';
        }

        $byLocale = $payload[self::KEY_HTML_BY_LOCALE] ?? null;
        if (is_array($byLocale)) {
            foreach ($activeLocales as $loc) {
                $raw = $byLocale[$loc] ?? '';
                $map[$loc] = is_string($raw) ? $raw : '';
            }

            return $map;
        }

        $legacy = $payload[self::KEY_HTML] ?? null;
        if (is_string($legacy) && $legacy !== '' && array_key_exists($defaultLocale, $map)) {
            $map[$defaultLocale] = $legacy;
        }

        return $map;
    }

    /**
     * @brief Whether About presentation HTML was explicitly persisted in the profile JSON.
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return bool
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function hasPersistedPresentation(array $payload): bool
    {
        return array_key_exists(self::KEY_HTML_BY_LOCALE, $payload)
            || array_key_exists(self::KEY_HTML, $payload);
    }

    /**
     * @brief Pick HTML for the current display locale with deterministic fallback (request locale → default → first non-empty in active order).
     *
     * @param array<string, string> $htmlByLocale Sanitized strings keyed by locale.
     * @param string $displayLocale Request or viewer locale.
     * @param string $defaultLocale Site default locale.
     * @param list<string> $activeLocalesOrder Active locales in preference order for fallback.
     * @return string HTML for the resolved locale or empty string.
     * @date 2026-05-10
     * @author Stephane H.
     */
    public static function pickPresentationHtmlForLocale(
        array $htmlByLocale,
        string $displayLocale,
        string $defaultLocale,
        array $activeLocalesOrder
    ): string {
        if (($htmlByLocale[$displayLocale] ?? '') !== '') {
            return $htmlByLocale[$displayLocale];
        }

        if (($htmlByLocale[$defaultLocale] ?? '') !== '') {
            return $htmlByLocale[$defaultLocale];
        }

        foreach ($activeLocalesOrder as $loc) {
            if (($htmlByLocale[$loc] ?? '') !== '') {
                return $htmlByLocale[$loc];
            }
        }

        return '';
    }

    /**
     * @brief Build a short cache-busting suffix for `/css/cv-about-profile.css` from atmosphere and photo placement inputs.
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @param int $profileId CvProfile primary key (0 when placeholder).
     * @return string Hex fingerprint (8 chars).
     * @date 2026-05-23
     * @author Stephane H.
     */
    public static function stylesheetCacheSuffixFromPayload(array $payload, int $profileId): string
    {
        $canonical = json_encode([
            'profileId' => $profileId,
            'atmosphere' => $payload['aboutSectionAtmosphereStyle'] ?? '',
            'sectionPattern' => AboutSectionPatternCustomizationContract::fingerprintFromPayload($payload),
            'presentationTypography' => AboutPresentationTypographyContract::fingerprintFromPayload($payload),
        ], JSON_THROW_ON_ERROR);

        return substr(hash('crc32b', $canonical), 0, 8);
    }
}
