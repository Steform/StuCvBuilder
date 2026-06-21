<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Global About presentation rich-text font sizes (all locales) stored in CvProfile JSON.
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
final class AboutPresentationTypographyContract
{
    public const KEY = 'aboutPresentationTypography';

    public const ELEMENT_H1 = 'h1';

    public const ELEMENT_H2 = 'h2';

    public const ELEMENT_H3 = 'h3';

    public const ELEMENT_H4 = 'h4';

    public const ELEMENT_H5 = 'h5';

    public const ELEMENT_H6 = 'h6';

    public const ELEMENT_P = 'p';

    /** @var list<string> */
    public const ELEMENT_KEYS = [
        self::ELEMENT_H1,
        self::ELEMENT_H2,
        self::ELEMENT_H3,
        self::ELEMENT_H4,
        self::ELEMENT_H5,
        self::ELEMENT_H6,
        self::ELEMENT_P,
    ];

    /** @var list<string> */
    public const ALLOWED_UNITS = ['rem', 'em', 'px', '%'];

    /** @var array<string, array{value: string, unit: string}> */
    public const DEFAULTS = [
        self::ELEMENT_H1 => ['value' => '2.25', 'unit' => 'rem'],
        self::ELEMENT_H2 => ['value' => '1.75', 'unit' => 'rem'],
        self::ELEMENT_H3 => ['value' => '1.375', 'unit' => 'rem'],
        self::ELEMENT_H4 => ['value' => '1.125', 'unit' => 'rem'],
        self::ELEMENT_H5 => ['value' => '1', 'unit' => 'rem'],
        self::ELEMENT_H6 => ['value' => '0.875', 'unit' => 'rem'],
        self::ELEMENT_P => ['value' => '1', 'unit' => 'rem'],
    ];

    /**
     * @brief Normalize stored typography map with defaults for each heading and paragraph.
     *
     * @param mixed $raw Raw payload value.
     * @return array<string, string> Map element key => CSS font-size (e.g. `2.25rem`).
     * @date 2026-05-23
     * @author Stephane H.
     */
    public static function normalize(mixed $raw): array
    {
        $map = is_array($raw) ? $raw : [];
        $out = [];
        foreach (self::ELEMENT_KEYS as $elementKey) {
            $out[$elementKey] = self::formatFontSize(
                self::sanitizeElement($map[$elementKey] ?? null, $elementKey)
            );
        }

        return $out;
    }

    /**
     * @brief Read typography from full profile payload.
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return array<string, string> Normalized font sizes per element.
     * @date 2026-05-23
     * @author Stephane H.
     */
    public static function fromPayload(array $payload): array
    {
        return self::normalize($payload[self::KEY] ?? null);
    }

    /**
     * @brief Merge admin POST `about_presentation_typography` into profile payload.
     *
     * @param array<string, mixed> $payload Existing CvProfile JSON payload.
     * @param array<string, mixed> $submitted Raw request map keyed by element.
     * @return array<string, mixed> Updated payload.
     * @date 2026-05-23
     * @author Stephane H.
     */
    public static function mergeSubmittedIntoPayload(array $payload, array $submitted): array
    {
        $existing = self::fromPayload($payload);
        $stored = [];
        foreach (self::ELEMENT_KEYS as $elementKey) {
            $row = $submitted[$elementKey] ?? null;
            if (!is_array($row)) {
                $stored[$elementKey] = self::parseFontSizeString($existing[$elementKey], $elementKey);

                continue;
            }

            $stored[$elementKey] = self::sanitizeElement($row, $elementKey);
        }

        $payload[self::KEY] = $stored;

        return $payload;
    }

    /**
     * @brief Split a normalized CSS font-size into value and unit for admin inputs.
     *
     * @param string $fontSize CSS font-size string.
     * @return array{value: string, unit: string}
     * @date 2026-05-23
     * @author Stephane H.
     */
    public static function splitFontSize(string $fontSize, string $elementKey = self::ELEMENT_P): array
    {
        $parsed = self::parseFontSizeString($fontSize, $elementKey);

        return [
            'value' => $parsed['value'],
            'unit' => $parsed['unit'],
        ];
    }

    /**
     * @brief Build a short fingerprint for stylesheet cache busting.
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return string Hex fragment.
     * @date 2026-05-23
     * @author Stephane H.
     */
    public static function fingerprintFromPayload(array $payload): string
    {
        $canonical = json_encode(self::fromPayload($payload), JSON_THROW_ON_ERROR);

        return substr(hash('crc32b', $canonical), 0, 8);
    }

    /**
     * @brief Sanitize one element row from storage or POST.
     *
     * @param mixed $raw Element value (array with value/unit or legacy CSS string).
     * @param string $elementKey Element key for default lookup.
     * @return array{value: string, unit: string}
     * @date 2026-05-23
     * @author Stephane H.
     */
    private static function sanitizeElement(mixed $raw, string $elementKey): array
    {
        $default = self::DEFAULTS[$elementKey] ?? self::DEFAULTS[self::ELEMENT_P];

        if (is_string($raw)) {
            return self::parseFontSizeString($raw, $elementKey);
        }

        if (!is_array($raw)) {
            return $default;
        }

        $valueRaw = $raw['value'] ?? '';
        $unitRaw = $raw['unit'] ?? '';
        if (!is_scalar($valueRaw) || !is_scalar($unitRaw)) {
            return $default;
        }

        $value = trim((string) $valueRaw);
        $unit = strtolower(trim((string) $unitRaw));
        if ($value === '') {
            return $default;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(rem|em|px|%)$/i', $value, $combinedMatches) === 1) {
            $unit = strtolower($combinedMatches[2]);
            $value = self::trimNumericString($combinedMatches[1]);
        }

        if (!in_array($unit, self::ALLOWED_UNITS, true)) {
            return $default;
        }

        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            return $default;
        }

        $numeric = (float) $value;
        if ($numeric <= 0 || $numeric > 512) {
            return $default;
        }

        return ['value' => self::trimNumericString($value), 'unit' => $unit];
    }

    /**
     * @brief Parse stored CSS font-size into value and unit.
     *
     * @param string $fontSize CSS font-size.
     * @return array{value: string, unit: string}
     * @date 2026-05-23
     * @author Stephane H.
     */
    private static function parseFontSizeString(string $fontSize, string $elementKey): array
    {
        $default = self::DEFAULTS[$elementKey] ?? self::DEFAULTS[self::ELEMENT_P];
        $fontSize = trim($fontSize);
        if (preg_match('/^(\d+(?:\.\d+)?)(rem|em|px|%)$/i', $fontSize, $matches) !== 1) {
            return $default;
        }

        $unit = strtolower($matches[2]);
        if (!in_array($unit, self::ALLOWED_UNITS, true)) {
            return $default;
        }

        return [
            'value' => self::trimNumericString($matches[1]),
            'unit' => $unit,
        ];
    }

    /**
     * @brief Format sanitized element as CSS font-size.
     *
     * @param array{value: string, unit: string} $element Sanitized element.
     * @return string CSS font-size.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private static function formatFontSize(array $element): string
    {
        return $element['value'].$element['unit'];
    }

    /**
     * @brief Trim trailing zeros from a positive decimal string.
     *
     * @param string $value Numeric string.
     * @return string Normalized numeric string.
     * @date 2026-05-23
     * @author Stephane H.
     */
    private static function trimNumericString(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
