<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Site-wide customizable colors stored on {@see \App\Entity\HomeCustomization}.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class SiteColorsContract
{
    public const FIELD_ACCENT = 'accent';

    public const FIELD_CV_MENU_BACKGROUND = 'cvMenuBackground';

    public const DEFAULT_CV_MENU_BACKGROUND_HEX = '#17283c';

    public const DEFAULT_ACCENT_HEX = '#1e5a96';

    /**
     * @brief Normalize persisted site colors map with defaults.
     *
     * @param mixed $raw Raw JSON-decoded value or null.
     * @return array{accent: string|null, cvMenuBackground: string|null}
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function normalize(mixed $raw): array
    {
        if (!is_array($raw)) {
            return ['accent' => null, 'cvMenuBackground' => null];
        }

        $accent = AboutSectionPatternCustomizationContract::sanitizeHexColor($raw[self::FIELD_ACCENT] ?? null);
        $cvMenuBackground = AboutSectionPatternCustomizationContract::sanitizeHexColor($raw[self::FIELD_CV_MENU_BACKGROUND] ?? null);

        return ['accent' => $accent, 'cvMenuBackground' => $cvMenuBackground];
    }

    /**
     * @brief Encode normalized map for JSON persistence.
     *
     * @param array{accent: string|null, cvMenuBackground: string|null} $colors Normalized site colors map.
     * @return string|null JSON string or null when no custom colors are stored.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function encodeForStorage(array $colors): ?string
    {
        $accent = AboutSectionPatternCustomizationContract::sanitizeHexColor($colors['accent'] ?? null);
        $cvMenuBackground = AboutSectionPatternCustomizationContract::sanitizeHexColor($colors['cvMenuBackground'] ?? null);
        if ($accent === null && $cvMenuBackground === null) {
            return null;
        }

        $payload = [];
        if ($accent !== null) {
            $payload[self::FIELD_ACCENT] = $accent;
        }
        if ($cvMenuBackground !== null) {
            $payload[self::FIELD_CV_MENU_BACKGROUND] = $cvMenuBackground;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @brief Decode JSON column value into normalized map.
     *
     * @param string|null $json Stored JSON from HomeCustomization.
     * @return array{accent: string|null, cvMenuBackground: string|null}
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function decodeFromStorage(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return self::normalize(null);
        }

        $decoded = json_decode($json, true);

        return self::normalize(is_array($decoded) ? $decoded : null);
    }

    /**
     * @brief Resolve accent hex with site override, profile fallback, then default.
     *
     * @param array{accent: string|null, cvMenuBackground: string|null} $siteColors Normalized site colors map.
     * @param string|null $profileFallbackBaseColor Legacy About pattern base color from CvProfile.
     * @return string Resolved `#rrggbb` accent color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function resolveAccent(array $siteColors, ?string $profileFallbackBaseColor = null): string
    {
        $accent = AboutSectionPatternCustomizationContract::sanitizeHexColor($siteColors['accent'] ?? null);
        if ($accent !== null) {
            return $accent;
        }

        $profileAccent = AboutSectionPatternCustomizationContract::sanitizeHexColor($profileFallbackBaseColor);

        return $profileAccent ?? self::DEFAULT_ACCENT_HEX;
    }

    /**
     * @brief Resolve public CV sidebar/menu background color.
     *
     * @param array{accent: string|null, cvMenuBackground: string|null} $siteColors Normalized site colors map.
     * @return string Resolved `#rrggbb` menu background color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function resolveCvMenuBackground(array $siteColors): string
    {
        $menuBackground = AboutSectionPatternCustomizationContract::sanitizeHexColor($siteColors['cvMenuBackground'] ?? null);

        return $menuBackground ?? self::DEFAULT_CV_MENU_BACKGROUND_HEX;
    }

    /**
     * @brief Keep About pattern base color separate from the site accent color.
     *
     * Light-mode pattern surfaces stay on the profile gray base; site accent is applied
     * only through {@see CvAboutPatternCssBuilder::buildCss()} accent argument.
     *
     * @param array<string, mixed> $pattern Normalized About pattern map.
     * @param array{accent: string|null, cvMenuBackground: string|null} $siteColors Normalized site colors map.
     * @return array<string, mixed> Unchanged pattern map (accent kept separate from pattern base).
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function applyAccentToPattern(array $pattern, array $siteColors): array
    {
        unset($siteColors);

        return $pattern;
    }

    /**
     * @brief Merge admin POST site color fields into normalized site colors map.
     *
     * @param array{accent: string|null, cvMenuBackground: string|null} $existing Existing normalized site colors map.
     * @param array<string, mixed> $submitted Raw `site_colors` request map.
     * @return array{accent: string|null, cvMenuBackground: string|null} Updated normalized map.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function mergeSubmitted(array $existing, array $submitted): array
    {
        $merged = $existing;

        $accent = AboutSectionPatternCustomizationContract::sanitizeHexColor($submitted['accent'] ?? null);
        if ($accent !== null) {
            $merged['accent'] = $accent;
        }

        $cvMenuBackground = AboutSectionPatternCustomizationContract::sanitizeHexColor($submitted['cv_menu_background'] ?? null);
        if ($cvMenuBackground !== null) {
            $merged['cvMenuBackground'] = $cvMenuBackground;
        }

        return $merged;
    }

    /**
     * @brief Merge admin POST accent field into normalized site colors map.
     *
     * @param array{accent: string|null, cvMenuBackground: string|null} $existing Existing normalized site colors map.
     * @param mixed $accentSubmitted Raw `site_colors[accent]` value.
     * @return array{accent: string|null, cvMenuBackground: string|null} Updated normalized map.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function mergeSubmittedAccent(array $existing, mixed $accentSubmitted): array
    {
        return self::mergeSubmitted($existing, is_array($accentSubmitted) ? $accentSubmitted : ['accent' => $accentSubmitted]);
    }

    /**
     * @brief Build cache-busting suffix for About pattern CSS including site accent.
     *
     * @param array{accent: string|null, cvMenuBackground: string|null} $siteColors Normalized site colors map.
     * @param array<string, mixed> $profilePayload CvProfile JSON payload.
     * @return string Short fingerprint hash fragment.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function patternCssCacheSuffix(array $siteColors, array $profilePayload): string
    {
        $pattern = AboutSectionPatternCustomizationContract::fromPayload($profilePayload);
        $accent = self::resolveAccent(
            $siteColors,
            is_string($pattern['baseColor'] ?? null) ? $pattern['baseColor'] : null
        );
        $pencilDecoration = CvPencilDecorationContract::fromPayload($profilePayload);
        $canonical = json_encode($pattern, JSON_THROW_ON_ERROR)
            .json_encode(['accent' => $accent], JSON_THROW_ON_ERROR)
            .json_encode($pencilDecoration, JSON_THROW_ON_ERROR)
            .json_encode($siteColors, JSON_THROW_ON_ERROR);

        return substr(hash('crc32b', $canonical), 0, 8);
    }

    /**
     * @brief Build cache-busting suffix for public CV sidebar layout CSS.
     *
     * @param array{accent: string|null, cvMenuBackground: string|null} $siteColors Normalized site colors map.
     * @param string|null $profileFallbackBaseColor Legacy About pattern base color from CvProfile.
     * @return string Short fingerprint hash fragment.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function layoutCssCacheSuffix(array $siteColors, ?string $profileFallbackBaseColor = null): string
    {
        $canonical = json_encode([
            'accent' => self::resolveAccent($siteColors, $profileFallbackBaseColor),
            'cvMenuBackground' => self::resolveCvMenuBackground($siteColors),
        ], JSON_THROW_ON_ERROR);

        return substr(hash('crc32b', $canonical), 0, 8);
    }
}
