<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief JSON keys, bounds, and parsing helpers for CV Situation editorial content stored under CvProfile content_json.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
final class SituationContentContract
{
    public const KEY_CONTENT_BY_LOCALE = 'situationContentByLocale';

    public const MAX_SHORT_TEXT_LENGTH = 200;

    public const MAX_INTRO_LENGTH = 2000;

    public const MAX_CHIP_LABEL_LENGTH = 80;

    public const MIN_CHIPS_PER_LIST = 1;

    public const MAX_CHIPS_PER_LIST = 12;

    public const MAX_CHIP_LIST_DSL_LENGTH = 1200;

    /** @var list<string> */
    public const CHIP_VARIANTS = ['primary', 'secondary'];

    public const DEFAULT_CHIP_VARIANT = 'secondary';

    /**
     * @brief Parse situation content rows from admin POST for all active locales.
     *
     * @param Request $request HTTP request with nested `situation_content` array.
     * @param list<string> $activeLocales Site active locale codes.
     * @return array<string, array<string, mixed>>|null Null when structure is invalid.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function parseContentFromRequest(Request $request, array $activeLocales): ?array
    {
        $raw = $request->request->all('situation_content');
        if (!is_array($raw)) {
            return null;
        }

        $result = [];
        foreach ($activeLocales as $locale) {
            $localeRow = $raw[$locale] ?? null;
            if (!is_array($localeRow)) {
                return null;
            }

            $normalized = self::normalizeContentRow($localeRow);
            if ($normalized === null) {
                return null;
            }

            $result[$locale] = $normalized;
        }

        return $result;
    }

    /**
     * @brief Read content map from decoded CvProfile payload.
     *
     * @param array<string, mixed> $payload Decoded JSON.
     * @return array<string, array<string, mixed>>
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function contentByLocaleFromStoredPayload(array $payload): array
    {
        $raw = $payload[self::KEY_CONTENT_BY_LOCALE] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $locale => $row) {
            if (!is_string($locale) || !is_array($row)) {
                continue;
            }

            $normalized = self::normalizeContentRow($row);
            if ($normalized !== null) {
                $result[$locale] = $normalized;
            }
        }

        return $result;
    }

    /**
     * @brief Whether persisted JSON already contains the situation content map key.
     *
     * @param array<string, mixed> $payload Decoded JSON.
     * @return bool
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function hasPersistedContentMap(array $payload): bool
    {
        return array_key_exists(self::KEY_CONTENT_BY_LOCALE, $payload);
    }

    /**
     * @brief Parse a compact chip list DSL string into normalized chip rows.
     *
     * @param string $raw DSL such as `France:primary;Lituanie:secondary`.
     * @return list<array{label: string, variant: string}>|null Null when invalid.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function parseChipListDsl(string $raw): ?array
    {
        $trimmed = trim(strip_tags($raw));
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_CHIP_LIST_DSL_LENGTH) {
            return null;
        }

        $segments = explode(';', $trimmed);
        $chips = [];
        foreach ($segments as $segment) {
            $segmentTrimmed = trim($segment);
            if ($segmentTrimmed === '') {
                continue;
            }

            $parts = explode(':', $segmentTrimmed, 2);
            if (count($parts) > 2) {
                return null;
            }

            $label = self::normalizeChipLabel($parts[0]);
            if ($label === null || $label === '') {
                return null;
            }

            $variant = self::DEFAULT_CHIP_VARIANT;
            if (count($parts) === 2) {
                $variantRaw = strtolower(trim($parts[1]));
                if (!in_array($variantRaw, self::CHIP_VARIANTS, true)) {
                    return null;
                }

                $variant = $variantRaw;
            }

            $chips[] = [
                'label' => $label,
                'variant' => $variant,
            ];
        }

        if (count($chips) < self::MIN_CHIPS_PER_LIST || count($chips) > self::MAX_CHIPS_PER_LIST) {
            return null;
        }

        return $chips;
    }

    /**
     * @brief Format normalized chip rows back to compact DSL for admin forms.
     *
     * @param list<array<string, mixed>>|mixed $chips Chip rows with label and variant keys.
     * @return string DSL string or empty when no chips.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function formatChipListToDsl(mixed $chips): string
    {
        if (!is_array($chips)) {
            return '';
        }

        $parts = [];
        foreach ($chips as $chip) {
            if (!is_array($chip)) {
                continue;
            }

            $label = is_string($chip['label'] ?? null) ? trim($chip['label']) : '';
            if ($label === '') {
                continue;
            }

            $variantRaw = is_string($chip['variant'] ?? null) ? strtolower(trim($chip['variant'])) : self::DEFAULT_CHIP_VARIANT;
            if (!in_array($variantRaw, self::CHIP_VARIANTS, true)) {
                $variantRaw = self::DEFAULT_CHIP_VARIANT;
            }

            $parts[] = $label.':'.$variantRaw;
        }

        return implode(';', $parts);
    }

    /**
     * @brief Attach DSL string fields for admin templates from normalized chip arrays.
     *
     * @param array<string, mixed> $content Normalized locale content.
     * @return array<string, mixed> Content with `searchWhereChipsDsl`, `searchModeChipsDsl`, `searchFocusChipsDsl`.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function attachAdminDslFields(array $content): array
    {
        $content['searchWhereChipsDsl'] = self::formatChipListToDsl($content['searchWhereChips'] ?? []);
        $content['searchModeChipsDsl'] = self::formatChipListToDsl($content['searchModeChips'] ?? []);
        $content['searchFocusChipsDsl'] = self::formatChipListToDsl($content['searchFocusChips'] ?? []);

        return $content;
    }

    /**
     * @brief Whether a normalized locale row has no meaningful visitor-facing text.
     *
     * @param array<string, mixed> $content Normalized content row.
     * @return bool
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function isLocaleContentEmpty(array $content): bool
    {
        foreach (['statusLabel', 'introLead', 'contractChip'] as $field) {
            if (($content[$field] ?? '') !== '') {
                return false;
            }
        }

        foreach (['searchWhereChips', 'searchModeChips', 'searchFocusChips'] as $listKey) {
            if (self::chipListHasContent($content[$listKey] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @brief Normalize a single locale content row from request or stored JSON.
     *
     * @param array<string, mixed> $row Raw row.
     * @return array<string, mixed>|null Null when invalid.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function normalizeContentRow(array $row): ?array
    {
        $row = self::migrateLegacyRow($row);

        $statusLabel = self::normalizeText($row['statusLabel'] ?? null, self::MAX_SHORT_TEXT_LENGTH);
        $introLead = self::normalizeText($row['introLead'] ?? null, self::MAX_INTRO_LENGTH);
        $contractChip = self::normalizeText($row['contractChip'] ?? null, self::MAX_SHORT_TEXT_LENGTH);

        if ($statusLabel === null || $introLead === null || $contractChip === null) {
            return null;
        }

        $searchWhereChips = self::resolveChipList(
            $row['searchWhereChipsDsl'] ?? null,
            $row['searchWhereChips'] ?? null
        );
        $searchModeChips = self::resolveChipList(
            $row['searchModeChipsDsl'] ?? null,
            $row['searchModeChips'] ?? null
        );
        $searchFocusChips = self::resolveChipList(
            $row['searchFocusChipsDsl'] ?? null,
            $row['searchFocusChips'] ?? null
        );

        if ($searchWhereChips === null || $searchModeChips === null || $searchFocusChips === null) {
            return null;
        }

        return [
            'statusLabel' => $statusLabel,
            'introLead' => $introLead,
            'contractChip' => $contractChip,
            'searchWhereChips' => $searchWhereChips,
            'searchModeChips' => $searchModeChips,
            'searchFocusChips' => $searchFocusChips,
        ];
    }

    /**
     * @brief Upgrade legacy stored rows before normalization.
     *
     * @param array<string, mixed> $row Raw row.
     * @return array<string, mixed>
     * @date 2026-05-20
     * @author Stephane H.
     */
    private static function migrateLegacyRow(array $row): array
    {
        if (!isset($row['searchFocusChips']) || $row['searchFocusChips'] === []) {
            $legacyFocus = self::normalizeText($row['searchFocusChip'] ?? null, self::MAX_CHIP_LABEL_LENGTH);
            if ($legacyFocus !== null && $legacyFocus !== '') {
                $row['searchFocusChips'] = [
                    [
                        'label' => $legacyFocus,
                        'variant' => 'primary',
                    ],
                ];
            }
        }

        if (isset($row['searchWhereChips']) && is_array($row['searchWhereChips'])) {
            $migrated = [];
            foreach ($row['searchWhereChips'] as $item) {
                if (is_string($item) || is_numeric($item)) {
                    $label = self::normalizeChipLabel((string) $item);
                    if ($label !== null && $label !== '') {
                        $migrated[] = [
                            'label' => $label,
                            'variant' => self::DEFAULT_CHIP_VARIANT,
                        ];
                    }

                    continue;
                }

                if (is_array($item)) {
                    $migrated[] = $item;
                }
            }

            $row['searchWhereChips'] = $migrated;
        }

        return $row;
    }

    /**
     * @brief Resolve chip list from DSL input or structured array.
     *
     * @param mixed $dslValue Posted DSL string or null.
     * @param mixed $structuredValue Chip array from POST or stored JSON.
     * @return list<array{label: string, variant: string}>|null
     * @date 2026-05-20
     * @author Stephane H.
     */
    private static function resolveChipList(mixed $dslValue, mixed $structuredValue): ?array
    {
        if (is_string($dslValue) && trim($dslValue) !== '') {
            return self::parseChipListDsl($dslValue);
        }

        return self::normalizeChipList($structuredValue);
    }

    /**
     * @brief Normalize chip list from DSL string or structured array.
     *
     * @param mixed $value DSL string, array of objects, or legacy string list.
     * @return list<array{label: string, variant: string}>|null
     * @date 2026-05-20
     * @author Stephane H.
     */
    private static function normalizeChipList(mixed $value): ?array
    {
        if (is_string($value)) {
            return self::parseChipListDsl($value);
        }

        if (!is_array($value)) {
            return null;
        }

        $chips = [];
        foreach ($value as $item) {
            if (is_string($item) || is_numeric($item)) {
                $label = self::normalizeChipLabel((string) $item);
                if ($label === null || $label === '') {
                    return null;
                }

                $chips[] = [
                    'label' => $label,
                    'variant' => self::DEFAULT_CHIP_VARIANT,
                ];

                continue;
            }

            if (!is_array($item)) {
                return null;
            }

            $label = self::normalizeChipLabel($item['label'] ?? null);
            if ($label === null || $label === '') {
                return null;
            }

            $variantRaw = is_string($item['variant'] ?? null) ? strtolower(trim((string) $item['variant'])) : '';
            if (!in_array($variantRaw, self::CHIP_VARIANTS, true)) {
                return null;
            }

            $chips[] = [
                'label' => $label,
                'variant' => $variantRaw,
            ];
        }

        if (count($chips) < self::MIN_CHIPS_PER_LIST || count($chips) > self::MAX_CHIPS_PER_LIST) {
            return null;
        }

        return $chips;
    }

    /**
     * @brief Whether a chip list contains at least one non-empty label.
     *
     * @param mixed $value Chip list value.
     * @return bool
     * @date 2026-05-20
     * @author Stephane H.
     */
    private static function chipListHasContent(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $chip) {
            if (is_string($chip) && trim($chip) !== '') {
                return true;
            }

            if (is_array($chip) && trim((string) ($chip['label'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value Raw chip label.
     * @return string|null Null when invalid characters are present.
     */
    private static function normalizeChipLabel(mixed $value): ?string
    {
        $normalized = self::normalizeText($value, self::MAX_CHIP_LABEL_LENGTH);
        if ($normalized === null || $normalized === '') {
            return $normalized;
        }

        if (str_contains($normalized, ';') || str_contains($normalized, ':')) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param mixed $value Raw value.
     * @return string|null Empty string when absent; null when invalid length.
     */
    private static function normalizeText(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $trimmed = trim(strip_tags((string) $value));
        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) > $maxLength) {
            $trimmed = mb_substr($trimmed, 0, $maxLength);
        }

        return $trimmed;
    }
}
