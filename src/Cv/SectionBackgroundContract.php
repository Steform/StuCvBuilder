<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Contract for `sectionBackgrounds` map in CV profile JSON (texture, colors, filter intensity).
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
final class SectionBackgroundContract
{
    public const KEY = 'sectionBackgrounds';

    public const COLOR_MODE_ABOUT = 'about';

    public const COLOR_MODE_CUSTOM = 'custom';

    public const DEFAULT_FILTER_INTENSITY_LIGHT = 0.22;

    public const DEFAULT_FILTER_INTENSITY_DARK = 0.32;

    public const MIN_FILTER_INTENSITY = 0.05;

    public const MAX_FILTER_INTENSITY = 0.95;

    /** @brief Reference intensity for light theme (maps to legacy overlay alpha at default slider). */
    public const REFERENCE_FILTER_INTENSITY_LIGHT = 0.22;

    /** @brief Reference intensity for dark theme. */
    public const REFERENCE_FILTER_INTENSITY_DARK = 0.32;

    /** @brief Reference overlay alpha on stacked gradient layer ({@see docs/reference/legacy-cv-texture-coloring.md}). */
    public const REFERENCE_LEGACY_OVERLAY_ALPHA = 0.65;

    public const MIN_LEGACY_OVERLAY_ALPHA = 0.18;

    public const MAX_LEGACY_OVERLAY_ALPHA = 0.92;

    public const MIN_ABOUT_COLOR_ADJUST_PERCENT = -100;

    public const MAX_ABOUT_COLOR_ADJUST_PERCENT = 100;

    public const DEFAULT_ABOUT_COLOR_ADJUST_PERCENT = 0;

    /** @var array<string, string> */
    public const LEGACY_TEXTURE_KEYS = [
        'situation' => 'situationBackgroundTexture',
        'experience' => 'experienceBackgroundTexture',
        'languages' => 'languagesBackgroundTexture',
        'education' => 'educationBackgroundTexture',
        'certification' => 'certificationBackgroundTexture',
        'interests' => 'interestsBackgroundTexture',
        'web_profiles' => 'webProfilesBackgroundTexture',
        'references' => 'referencesBackgroundTexture',
    ];

    /**
     * @brief Default background block for one eligible section.
     *
     * @param string|null $textureOverride Optional texture slug when migrating legacy keys.
     * @return array<string, mixed> Normalized section background block.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function defaultBlockForSection(?string $textureOverride = null): array
    {
        return [
            'texture' => SituationBackgroundTexture::fromStored($textureOverride)->value,
            'colorMode' => self::COLOR_MODE_ABOUT,
            'primary' => null,
            'secondary' => null,
            'filterIntensityLight' => self::DEFAULT_FILTER_INTENSITY_LIGHT,
            'filterIntensityDark' => self::DEFAULT_FILTER_INTENSITY_DARK,
            'aboutColorAdjustPercent' => self::DEFAULT_ABOUT_COLOR_ADJUST_PERCENT,
        ];
    }

    /**
     * @brief Normalize a raw stored map with legacy texture migration and full eligible keys.
     *
     * @param mixed $raw Raw `sectionBackgrounds` value from content JSON.
     * @param array<string, mixed> $payload Full profile payload for legacy `situationBackgroundTexture` keys.
     * @return array<string, array<string, mixed>> Map section slug => normalized block.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function normalizeMap(mixed $raw, array $payload = []): array
    {
        $stored = is_array($raw) ? $raw : [];
        $normalized = [];

        foreach (SectionTransitionContract::ELIGIBLE_SECTION_KEYS as $sectionKey) {
            $legacyTexture = null;
            if (isset(self::LEGACY_TEXTURE_KEYS[$sectionKey])) {
                $legacyKey = self::LEGACY_TEXTURE_KEYS[$sectionKey];
                $legacyTexture = is_string($payload[$legacyKey] ?? null) ? $payload[$legacyKey] : null;
            }

            $blockRaw = $stored[$sectionKey] ?? null;
            if (!is_array($blockRaw) && $legacyTexture === null) {
                $normalized[$sectionKey] = self::defaultBlockForSection();

                continue;
            }

            $normalized[$sectionKey] = self::normalizeBlock(
                is_array($blockRaw) ? $blockRaw : [],
                $legacyTexture
            );
        }

        return $normalized;
    }

    /**
     * @brief Normalize one section background block from stored or submitted data.
     *
     * @param array<string, mixed> $block Raw block fields.
     * @param string|null $legacyTexture Fallback texture from legacy top-level key.
     * @return array<string, mixed> Sanitized block.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function normalizeBlock(array $block, ?string $legacyTexture = null): array
    {
        $texture = SituationBackgroundTexture::fromStored($block['texture'] ?? $legacyTexture)->value;

        $colorMode = is_string($block['colorMode'] ?? null) && $block['colorMode'] === self::COLOR_MODE_CUSTOM
            ? self::COLOR_MODE_CUSTOM
            : self::COLOR_MODE_ABOUT;

        $primary = self::sanitizeHexColor($block['primary'] ?? null);
        $secondary = self::sanitizeHexColor($block['secondary'] ?? null);

        if ($colorMode === self::COLOR_MODE_CUSTOM && ($primary === null || $secondary === null)) {
            $colorMode = self::COLOR_MODE_ABOUT;
            $primary = null;
            $secondary = null;
        }

        return [
            'texture' => $texture,
            'colorMode' => $colorMode,
            'primary' => $colorMode === self::COLOR_MODE_CUSTOM ? $primary : null,
            'secondary' => $colorMode === self::COLOR_MODE_CUSTOM ? $secondary : null,
            'filterIntensityLight' => self::normalizeIntensity($block['filterIntensityLight'] ?? null, self::DEFAULT_FILTER_INTENSITY_LIGHT),
            'filterIntensityDark' => self::normalizeIntensity($block['filterIntensityDark'] ?? null, self::DEFAULT_FILTER_INTENSITY_DARK),
            'aboutColorAdjustPercent' => self::normalizeAboutColorAdjustPercent($block['aboutColorAdjustPercent'] ?? null),
        ];
    }

    /**
     * @brief Merge submitted admin `section_backgrounds` for one section into payload and sync legacy keys.
     *
     * @param array<string, mixed> $payload Profile content JSON array.
     * @param string $sectionKey Eligible section slug.
     * @param mixed $submitted Request `section_backgrounds[{section}]` array or null.
     * @return array<string, mixed> Updated payload.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function mergeSubmittedSectionIntoPayload(array $payload, string $sectionKey, mixed $submitted): array
    {
        if (!in_array($sectionKey, SectionTransitionContract::ELIGIBLE_SECTION_KEYS, true)) {
            return $payload;
        }

        $existingMap = self::normalizeMap($payload[self::KEY] ?? null, $payload);
        if (is_array($submitted)) {
            $existingBlock = $existingMap[$sectionKey] ?? self::defaultBlockForSection();
            $existingMap[$sectionKey] = self::normalizeBlock(
                array_merge($existingBlock, $submitted),
                is_string($existingBlock['texture'] ?? null) ? $existingBlock['texture'] : null
            );
        }

        $payload[self::KEY] = $existingMap;

        return self::syncLegacyTextureKeys($payload);
    }

    /**
     * @brief Merge full submitted `section_backgrounds` map when multiple sections are posted at once.
     *
     * @param array<string, mixed> $payload Profile content JSON array.
     * @param mixed $submitted Request `section_backgrounds` array or null.
     * @return array<string, mixed> Updated payload.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function mergeSubmittedIntoPayload(array $payload, mixed $submitted): array
    {
        $existingMap = self::normalizeMap($payload[self::KEY] ?? null, $payload);

        if (is_array($submitted)) {
            foreach (SectionTransitionContract::ELIGIBLE_SECTION_KEYS as $sectionKey) {
                if (!array_key_exists($sectionKey, $submitted)) {
                    continue;
                }
                $sectionSubmitted = $submitted[$sectionKey];
                if (!is_array($sectionSubmitted)) {
                    continue;
                }
                $existingBlock = $existingMap[$sectionKey] ?? self::defaultBlockForSection();
                $existingMap[$sectionKey] = self::normalizeBlock(
                    array_merge($existingBlock, $sectionSubmitted),
                    is_string($existingBlock['texture'] ?? null) ? $existingBlock['texture'] : null
                );
            }
        }

        $payload[self::KEY] = $existingMap;

        return self::syncLegacyTextureKeys($payload);
    }

    /**
     * @brief Write normalized map to payload and mirror situation/experience legacy texture keys.
     *
     * @param array<string, mixed> $payload Profile content JSON array.
     * @return array<string, mixed> Payload with `sectionBackgrounds` and legacy keys.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function applyNormalizedMapToPayload(array $payload): array
    {
        $normalized = self::normalizeMap($payload[self::KEY] ?? null, $payload);
        $payload[self::KEY] = $normalized;

        return self::syncLegacyTextureKeys($payload);
    }

    /**
     * @brief Keep legacy top-level texture keys in sync for cache consumers and tests.
     *
     * @param array<string, mixed> $payload Profile content JSON array.
     * @return array<string, mixed> Payload with legacy keys updated when applicable.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function syncLegacyTextureKeys(array $payload): array
    {
        $map = is_array($payload[self::KEY] ?? null) ? $payload[self::KEY] : [];
        foreach (self::LEGACY_TEXTURE_KEYS as $sectionKey => $legacyKey) {
            if (!isset($map[$sectionKey]) || !is_array($map[$sectionKey])) {
                continue;
            }
            $texture = $map[$sectionKey]['texture'] ?? null;
            if (is_string($texture) && $texture !== '') {
                $payload[$legacyKey] = SituationBackgroundTexture::fromStored($texture)->value;
            }
        }

        return $payload;
    }

    /**
     * @brief Resolve texture slug for a section from normalized map or legacy keys.
     *
     * @param array<string, mixed> $payload Resolved or raw profile payload.
     * @param string $sectionKey Eligible section slug.
     * @return string Texture slug safe for BEM suffix.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function resolveTextureForSection(array $payload, string $sectionKey): string
    {
        $map = self::normalizeMap($payload[self::KEY] ?? null, $payload);

        return SituationBackgroundTexture::fromStored($map[$sectionKey]['texture'] ?? null)->value;
    }

    /**
     * @brief Clamp filter intensity to allowed range.
     *
     * @param mixed $raw Submitted or stored value.
     * @param float $default Fallback when invalid.
     * @return float Clamped intensity between {@see MIN_FILTER_INTENSITY} and {@see MAX_FILTER_INTENSITY}.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function normalizeIntensity(mixed $raw, float $default): float
    {
        if (!is_numeric($raw)) {
            return $default;
        }

        $value = (float) $raw;

        return max(self::MIN_FILTER_INTENSITY, min(self::MAX_FILTER_INTENSITY, round($value, 4)));
    }

    /**
     * @brief Clamp About tone adjustment percent (-100 darken, 0 neutral, +100 lighten).
     *
     * @param mixed $raw Submitted or stored value.
     * @return int Clamped integer percent.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function normalizeAboutColorAdjustPercent(mixed $raw): int
    {
        if (!is_numeric($raw)) {
            return self::DEFAULT_ABOUT_COLOR_ADJUST_PERCENT;
        }

        return max(
            self::MIN_ABOUT_COLOR_ADJUST_PERCENT,
            min(self::MAX_ABOUT_COLOR_ADJUST_PERCENT, (int) round((float) $raw))
        );
    }

    /**
     * @brief Lighten or darken a hex color relative to About palette tones.
     *
     * @param string $hex Normalized `#rrggbb` color.
     * @param int $adjustPercent Negative values darken, positive values lighten.
     * @return string Adjusted `#rrggbb` color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public static function adjustHexByAboutTone(string $hex, int $adjustPercent): string
    {
        $sanitized = self::sanitizeHexColor($hex) ?? '#03215a';
        $adjustPercent = self::normalizeAboutColorAdjustPercent($adjustPercent);
        if ($adjustPercent === 0) {
            return $sanitized;
        }

        $rgb = self::hexToRgb($sanitized);
        if ($adjustPercent > 0) {
            $ratio = $adjustPercent / 100;
            $r = (int) round($rgb['r'] + (255 - $rgb['r']) * $ratio);
            $g = (int) round($rgb['g'] + (255 - $rgb['g']) * $ratio);
            $b = (int) round($rgb['b'] + (255 - $rgb['b']) * $ratio);
        } else {
            $ratio = abs($adjustPercent) / 100;
            $r = (int) round($rgb['r'] * (1 - $ratio));
            $g = (int) round($rgb['g'] * (1 - $ratio));
            $b = (int) round($rgb['b'] * (1 - $ratio));
        }

        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b))
        );
    }

    /**
     * @brief Map filter intensity to legacy stacked-gradient overlay alpha (0.65 at reference intensity).
     *
     * @param float $intensity Normalized filter intensity (0.05–0.95).
     * @param bool $isDarkTheme When true, use dark-theme reference intensity.
     * @return float Overlay alpha for rgba() on the top background-image layer.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function intensityToLegacyOverlayAlpha(float $intensity, bool $isDarkTheme = false): float
    {
        $refIntensity = $isDarkTheme ? self::REFERENCE_FILTER_INTENSITY_DARK : self::REFERENCE_FILTER_INTENSITY_LIGHT;
        $ratio = $refIntensity > 0.0 ? $intensity / $refIntensity : 1.0;
        $alpha = self::REFERENCE_LEGACY_OVERLAY_ALPHA * $ratio;

        return max(self::MIN_LEGACY_OVERLAY_ALPHA, min(self::MAX_LEGACY_OVERLAY_ALPHA, round($alpha, 3)));
    }

    /**
     * @brief Build legacy texture overlay variables for dynamic CSS emission.
     *
     * @param float $intensity Normalized filter intensity (0.05–0.95).
     * @param string $secondaryHex Section secondary color `#rrggbb` used for the gradient wash.
     * @param bool $isDarkTheme When true, use dark-theme reference intensity for alpha scaling.
     * @return array{overlayRgba: string} `overlayRgba` is a full `rgba(r,g,b,a)` value.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function intensityToLegacyTextureVars(float $intensity, string $secondaryHex, bool $isDarkTheme = false): array
    {
        $rgb = self::hexToRgb($secondaryHex);
        $alpha = self::intensityToLegacyOverlayAlpha($intensity, $isDarkTheme);

        return [
            'overlayRgba' => sprintf('rgba(%d, %d, %d, %s)', $rgb['r'], $rgb['g'], $rgb['b'], rtrim(rtrim(sprintf('%.3f', $alpha), '0'), '.')),
        ];
    }

    /**
     * @brief Parse `#rrggbb` hex to RGB channels for legacy rgba overlays.
     *
     * @param string $hex Normalized `#rrggbb` color.
     * @return array{r: int, g: int, b: int} RGB channels 0–255.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function hexToRgb(string $hex): array
    {
        $normalized = self::sanitizeHexColor($hex) ?? '#03215a';
        $value = substr($normalized, 1);

        return [
            'r' => (int) hexdec(substr($value, 0, 2)),
            'g' => (int) hexdec(substr($value, 2, 2)),
            'b' => (int) hexdec(substr($value, 4, 2)),
        ];
    }

    /**
     * @brief Sanitize optional hex color to `#rrggbb` or null.
     *
     * @param mixed $raw Raw color input.
     * @return string|null Normalized hex or null when invalid.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function sanitizeHexColor(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = strtolower(trim($raw));
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $trimmed)) {
            return null;
        }

        if (strlen($trimmed) === 4) {
            return '#'.$trimmed[1].$trimmed[1].$trimmed[2].$trimmed[2].$trimmed[3].$trimmed[3];
        }

        return $trimmed;
    }
}
