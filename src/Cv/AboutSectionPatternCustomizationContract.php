<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief About section pattern base color and per-tone white-mix percentages in CvProfile JSON.
 *
 * @date 2026-05-27
 * @author Stephane H.
 */
final class AboutSectionPatternCustomizationContract
{
    public const KEY = 'aboutSectionPatternCustomization';
    public const FIELD_PATTERN_ID = 'patternId';
    public const FIELD_PATTERN_LEFT_ID = 'patternLeftId';
    public const FIELD_PATTERN_RIGHT_ID = 'patternRightId';
    public const DEFAULT_PATTERN_LEFT_ID = 'fond-about-upload-94862fe23f';

    public const DEFAULT_PATTERN_RIGHT_ID = 'fond-about-upload-d955194be5';

    /** @deprecated Use {@see DEFAULT_PATTERN_LEFT_ID} for new defaults. */
    public const DEFAULT_PATTERN_ID = self::DEFAULT_PATTERN_LEFT_ID;

    /** @deprecated Migrated into {@see KEY} as `baseColor`. */
    public const LEGACY_COLOR_KEY = 'aboutSectionCustomizationColor';

    public const TONE_1 = 'tone1';

    public const TONE_2 = 'tone2';

    public const TONE_3 = 'tone3';

    public const TONE_4 = 'tone4';
    public const TONE_SIDE_LEFT = 'left';
    public const TONE_SIDE_RIGHT = 'right';

    /** @var list<string> */
    public const TONE_KEYS = [
        self::TONE_1,
        self::TONE_2,
        self::TONE_3,
        self::TONE_4,
    ];

    /** @var list<string> */
    public const TONE_SIDE_KEYS = [
        self::TONE_SIDE_LEFT,
        self::TONE_SIDE_RIGHT,
    ];

    public const DEFAULT_BASE_HEX = '#2d2d2d';

    public const DEFAULT_SURFACE_MIX_PERCENT = 62;
    public const DEFAULT_DARK_SURFACE_DARKEN_PERCENT = 18;
    public const DEFAULT_VERTICAL_ALIGN = 'center';
    public const DEFAULT_SCALE_MODE = 'cover';

    /** @var array<string, int> Default white-mix % per tone (0 = base only, 100 = white). */
    public const DEFAULT_TONE_MIX_PERCENT = [
        self::TONE_1 => 0,
        self::TONE_2 => 12,
        self::TONE_3 => 40,
        self::TONE_4 => 62,
    ];

    /**
     * @brief Normalize stored customization map with defaults.
     *
     * @param mixed $raw Raw payload value.
     * @return array{
     *     patternId: string,
     *     patternLeftId: string,
     *     patternRightId: string,
     *     baseColor: string,
     *     toneMixPercent: array{left: array<string, int>, right: array<string, int>},
     *     surfaceMixPercent: int,
     *     darkSurfaceDarkenPercent: int,
     * }
     * @date 2026-05-28
     * @author Stephane H.
     */
    public static function normalize(mixed $raw): array
    {
        $fromLegacy = self::normalizeFromLegacyPayload($raw);
        if ($fromLegacy !== null) {
            return $fromLegacy;
        }

        $map = is_array($raw) ? $raw : [];
        $legacyPatternId = self::normalizePatternId($map[self::FIELD_PATTERN_ID] ?? null);
        $patternLeftId = self::normalizePatternId($map[self::FIELD_PATTERN_LEFT_ID] ?? null)
            ?? $legacyPatternId
            ?? self::DEFAULT_PATTERN_LEFT_ID;
        $patternRightId = self::normalizePatternId($map[self::FIELD_PATTERN_RIGHT_ID] ?? null)
            ?? $legacyPatternId
            ?? self::DEFAULT_PATTERN_RIGHT_ID;
        $baseColor = self::sanitizeHexColor($map['baseColor'] ?? null) ?? self::DEFAULT_BASE_HEX;
        $toneMixPercent = self::normalizeToneMixPercentBySideMap($map['toneMixPercent'] ?? null);
        $surfaceMixPercent = self::normalizeSurfaceMixPercent(
            $map['surfaceMixPercent'] ?? null,
            $toneMixPercent[self::TONE_SIDE_LEFT] ?? self::DEFAULT_TONE_MIX_PERCENT
        );
        $darkSurfaceDarkenPercent = self::normalizeDarkSurfaceDarkenPercent(
            $map['darkSurfaceDarkenPercent'] ?? null
        );

        return [
            'patternId' => $patternLeftId,
            'patternLeftId' => $patternLeftId,
            'patternRightId' => $patternRightId,
            'baseColor' => $baseColor,
            'toneMixPercent' => $toneMixPercent,
            'surfaceMixPercent' => $surfaceMixPercent,
            'darkSurfaceDarkenPercent' => $darkSurfaceDarkenPercent,
        ];
    }

    /**
     * @brief Read customization from full profile payload (supports legacy string key).
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return array{
     *     patternId: string,
     *     patternLeftId: string,
     *     patternRightId: string,
     *     baseColor: string,
     *     toneMixPercent: array{left: array<string, int>, right: array<string, int>},
     *     surfaceMixPercent: int,
     *     darkSurfaceDarkenPercent: int,
     * }
     * @date 2026-05-28
     * @author Stephane H.
     */
    public static function fromPayload(array $payload): array
    {
        if (array_key_exists(self::KEY, $payload)) {
            return self::normalize($payload[self::KEY]);
        }

        if (array_key_exists(self::LEGACY_COLOR_KEY, $payload)) {
            $legacy = self::normalizeFromLegacyPayload($payload[self::LEGACY_COLOR_KEY]);
            if ($legacy !== null) {
                return $legacy;
            }
        }

        return self::normalize(null);
    }

    /**
     * @brief Merge admin POST fields into profile payload.
     *
     * @param array<string, mixed> $payload Existing CvProfile JSON payload.
     * @param mixed $baseColorSubmitted Raw `about_section_customization_color`.
     * @param array<string, mixed> $toneMixSubmitted Raw `about_section_pattern_tone_mix_percent` map by side.
     * @param mixed $surfaceMixSubmitted Raw `about_section_pattern_surface_mix_percent`.
     * @param mixed $darkSurfaceDarkenSubmitted Raw `about_section_pattern_dark_surface_darken_percent`.
     * @param mixed $patternLeftIdSubmitted Raw `about_section_pattern_template_left`.
     * @param mixed $patternRightIdSubmitted Raw `about_section_pattern_template_right`.
     * @return array<string, mixed> Updated payload.
     * @date 2026-05-28
     * @author Stephane H.
     */
    public static function mergeSubmittedIntoPayload(
        array $payload,
        mixed $baseColorSubmitted,
        array $toneMixSubmitted,
        mixed $surfaceMixSubmitted = null,
        mixed $darkSurfaceDarkenSubmitted = null,
        mixed $patternLeftIdSubmitted = null,
        mixed $patternRightIdSubmitted = null,
        mixed $patternIdSubmitted = null,
    ): array {
        $existing = self::fromPayload($payload);
        $baseColor = self::sanitizeHexColor($baseColorSubmitted) ?? $existing['baseColor'];
        $legacySubmittedPatternId = self::normalizeSubmittedPatternId($patternIdSubmitted);
        $patternLeftId = self::normalizeSubmittedPatternId($patternLeftIdSubmitted)
            ?? $legacySubmittedPatternId
            ?? $existing['patternLeftId'];
        $patternRightId = self::normalizeSubmittedPatternId($patternRightIdSubmitted)
            ?? $legacySubmittedPatternId
            ?? $existing['patternRightId'];
        $toneMixPercent = self::normalizeToneMixPercentBySideMap($toneMixSubmitted);
        if ($toneMixSubmitted === []) {
            $toneMixPercent = $existing['toneMixPercent'];
        }
        if ($surfaceMixSubmitted !== null && $surfaceMixSubmitted !== '') {
            $surfaceMixPercent = self::clampPercentInt($surfaceMixSubmitted);
        } else {
            $surfaceMixPercent = $existing['surfaceMixPercent'];
        }
        if ($darkSurfaceDarkenSubmitted !== null && $darkSurfaceDarkenSubmitted !== '') {
            $darkSurfaceDarkenPercent = self::normalizeDarkSurfaceDarkenPercent($darkSurfaceDarkenSubmitted);
        } else {
            $darkSurfaceDarkenPercent = $existing['darkSurfaceDarkenPercent'];
        }

        $payload[self::KEY] = [
            'patternId' => $patternLeftId,
            'patternLeftId' => $patternLeftId,
            'patternRightId' => $patternRightId,
            'baseColor' => $baseColor,
            'toneMixPercent' => $toneMixPercent,
            'surfaceMixPercent' => $surfaceMixPercent,
            'darkSurfaceDarkenPercent' => $darkSurfaceDarkenPercent,
        ];
        unset($payload[self::LEGACY_COLOR_KEY]);

        return $payload;
    }

    /**
     * @brief Normalize submitted pattern id and allow explicit empty value to disable SVG.
     *
     * @param mixed $raw Raw submitted value.
     * @return string|null Empty string means "no pattern"; null means invalid/unchanged.
     * @date 2026-05-28
     * @author Stephane H.
     */
    private static function normalizeSubmittedPatternId(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        if (trim($raw) === '') {
            return '';
        }

        return self::normalizePatternId($raw);
    }

    /**
     * @brief Short fingerprint for dynamic About pattern CSS cache busting.
     *
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return string Hex fragment.
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function fingerprintFromPayload(array $payload): string
    {
        $canonical = json_encode(self::fromPayload($payload), JSON_THROW_ON_ERROR);

        return substr(hash('crc32b', $canonical), 0, 8);
    }

    /**
     * @brief Sanitize optional hex color to `#rrggbb` or null.
     *
     * @param mixed $raw Raw color input.
     * @return string|null Normalized hex or null when invalid.
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function sanitizeHexColor(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = strtolower(trim($raw));
        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $trimmed)) {
            return null;
        }

        if (strlen($trimmed) === 4) {
            return '#'.$trimmed[1].$trimmed[1].$trimmed[2].$trimmed[2].$trimmed[3].$trimmed[3];
        }

        return $trimmed;
    }

    /**
     * @brief Clamp and normalize tone mix percentages for one side (0–100 integers).
     *
     * @param mixed $raw Raw map or null.
     * @return array<string, int>
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function normalizeToneMixPercentMap(mixed $raw): array
    {
        $result = self::DEFAULT_TONE_MIX_PERCENT;
        if (!is_array($raw)) {
            return $result;
        }

        foreach (self::TONE_KEYS as $toneKey) {
            if (!array_key_exists($toneKey, $raw)) {
                continue;
            }
            $result[$toneKey] = self::clampPercentInt($raw[$toneKey]);
        }

        return $result;
    }

    /**
     * @brief Normalize side-aware tone map with legacy fallback from flat tone map.
     *
     * @param mixed $raw Raw side map or legacy flat map.
     * @return array{left: array<string, int>, right: array<string, int>}
     * @date 2026-05-28
     * @author Stephane H.
     */
    public static function normalizeToneMixPercentBySideMap(mixed $raw): array
    {
        $default = [
            self::TONE_SIDE_LEFT => self::DEFAULT_TONE_MIX_PERCENT,
            self::TONE_SIDE_RIGHT => self::DEFAULT_TONE_MIX_PERCENT,
        ];

        if (!is_array($raw)) {
            return $default;
        }

        $legacyFlatToneMap = self::normalizeToneMixPercentMap($raw);
        $leftRaw = is_array($raw[self::TONE_SIDE_LEFT] ?? null) ? $raw[self::TONE_SIDE_LEFT] : null;
        $rightRaw = is_array($raw[self::TONE_SIDE_RIGHT] ?? null) ? $raw[self::TONE_SIDE_RIGHT] : null;

        return [
            self::TONE_SIDE_LEFT => $leftRaw !== null ? self::normalizeToneMixPercentMap($leftRaw) : $legacyFlatToneMap,
            self::TONE_SIDE_RIGHT => $rightRaw !== null ? self::normalizeToneMixPercentMap($rightRaw) : $legacyFlatToneMap,
        ];
    }

    /**
     * @brief Clamp and normalize section backdrop white-mix percent (0–100).
     *
     * @param mixed $raw Raw submitted value or null.
     * @param array<string, int> $toneMixPercent Normalized tone map used as fallback.
     * @return int Clamped percent.
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function normalizeSurfaceMixPercent(mixed $raw, array $toneMixPercent): int
    {
        if ($raw !== null && $raw !== '') {
            return self::clampPercentInt($raw);
        }

        return $toneMixPercent[self::TONE_4] ?? self::DEFAULT_SURFACE_MIX_PERCENT;
    }

    /**
     * @brief Clamp and normalize dark-mode backdrop darkening percent (0–100).
     *
     * @param mixed $raw Raw submitted value.
     * @return int Clamped percent.
     * @date 2026-05-28
     * @author Stephane H.
     */
    public static function normalizeDarkSurfaceDarkenPercent(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return self::DEFAULT_DARK_SURFACE_DARKEN_PERCENT;
        }

        return self::clampPercentInt($raw);
    }

    /**
     * @brief Build legacy-compatible map from old single-color payload value.
     *
     * @param mixed $raw Legacy string or nested map.
     * @return array{
     *     patternId: string,
     *     patternLeftId: string,
     *     patternRightId: string,
     *     baseColor: string,
     *     toneMixPercent: array{left: array<string, int>, right: array<string, int>},
     *     surfaceMixPercent: int,
     *     darkSurfaceDarkenPercent: int,
     * }|null
     * @date 2026-05-28
     * @author Stephane H.
     */
    private static function normalizeFromLegacyPayload(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $baseColor = self::sanitizeHexColor($raw);
            if ($baseColor === null) {
                return null;
            }

            return [
                'patternId' => self::DEFAULT_PATTERN_LEFT_ID,
                'patternLeftId' => self::DEFAULT_PATTERN_LEFT_ID,
                'patternRightId' => self::DEFAULT_PATTERN_RIGHT_ID,
                'baseColor' => $baseColor,
                'toneMixPercent' => [
                    self::TONE_SIDE_LEFT => self::DEFAULT_TONE_MIX_PERCENT,
                    self::TONE_SIDE_RIGHT => self::DEFAULT_TONE_MIX_PERCENT,
                ],
                'surfaceMixPercent' => self::DEFAULT_SURFACE_MIX_PERCENT,
                'darkSurfaceDarkenPercent' => self::DEFAULT_DARK_SURFACE_DARKEN_PERCENT,
            ];
        }

        return null;
    }

    /**
     * @brief Clamp arbitrary input to 0–100 integer percent.
     *
     * @param mixed $raw Raw submitted value.
     * @return int Clamped percent.
     * @date 2026-05-27
     * @author Stephane H.
     */
    private static function clampPercentInt(mixed $raw): int
    {
        if (is_string($raw) && is_numeric($raw)) {
            $raw = (int) $raw;
        }
        if (!is_int($raw) && !is_float($raw)) {
            return 0;
        }

        return max(0, min(100, (int) round($raw)));
    }

    /**
     * @brief Normalize pattern template identifier to safe slug format.
     *
     * @param mixed $raw Raw pattern id value.
     * @return string|null
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function normalizePatternId(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = strtolower(trim($raw));
        if ($trimmed === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @brief Normalize vertical anchor option for About pattern rendering.
     *
     * @param mixed $raw Raw vertical alignment value.
     * @return string
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function normalizeVerticalAlign(mixed $raw): string
    {
        if (!is_string($raw)) {
            return self::DEFAULT_VERTICAL_ALIGN;
        }

        $value = strtolower(trim($raw));
        if (!in_array($value, ['top', 'center', 'bottom'], true)) {
            return self::DEFAULT_VERTICAL_ALIGN;
        }

        return $value;
    }

    /**
     * @brief Normalize scale mode option for About pattern rendering.
     *
     * @param mixed $raw Raw scale mode value.
     * @return string
     * @date 2026-05-27
     * @author Stephane H.
     */
    public static function normalizeScaleMode(mixed $raw): string
    {
        if (!is_string($raw)) {
            return self::DEFAULT_SCALE_MODE;
        }

        $value = strtolower(trim($raw));
        if (!in_array($value, ['cover', 'fit'], true)) {
            return self::DEFAULT_SCALE_MODE;
        }

        return $value;
    }
}
