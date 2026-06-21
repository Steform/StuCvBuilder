<?php

declare(strict_types=1);

namespace App\Cv;

use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Public CV pencil decoration settings stored in CvProfile `contentJson`.
 *
 * @date 2026-06-08
 * @author Stephane H.
 */
final class CvPencilDecorationContract
{
    public const KEY = 'cvPencilDecoration';

    public const FIELD_ENABLED = 'enabled';

    public const FIELD_LIGHT_TONE_MIX_PERCENT = 'lightToneMixPercent';

    public const FIELD_DARK_TONE_MIX_PERCENT = 'darkToneMixPercent';

    public const DEFAULT_LIGHT_TONE_MIX_PERCENT = 93;

    public const DEFAULT_DARK_TONE_MIX_PERCENT = 90;

    private const MIN_TONE_MIX_PERCENT = 0;

    private const MAX_TONE_MIX_PERCENT = 100;

    /**
     * @brief Normalize stored or submitted pencil decoration map.
     *
     * @param mixed $raw Raw payload value.
     * @return array{
     *     enabled: bool,
     *     lightToneMixPercent: int,
     *     darkToneMixPercent: int
     * }
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function normalize(mixed $raw): array
    {
        if (!is_array($raw)) {
            return self::defaultMap();
        }

        return [
            self::FIELD_ENABLED => self::normalizeEnabled($raw[self::FIELD_ENABLED] ?? true),
            self::FIELD_LIGHT_TONE_MIX_PERCENT => self::normalizeToneMixPercent(
                $raw[self::FIELD_LIGHT_TONE_MIX_PERCENT] ?? null,
                self::DEFAULT_LIGHT_TONE_MIX_PERCENT
            ),
            self::FIELD_DARK_TONE_MIX_PERCENT => self::normalizeToneMixPercent(
                $raw[self::FIELD_DARK_TONE_MIX_PERCENT] ?? null,
                self::DEFAULT_DARK_TONE_MIX_PERCENT
            ),
        ];
    }

    /**
     * @brief Read normalized pencil decoration from a profile payload.
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return array{
     *     enabled: bool,
     *     lightToneMixPercent: int,
     *     darkToneMixPercent: int
     * }
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function fromPayload(array $payload): array
    {
        return self::normalize($payload[self::KEY] ?? null);
    }

    /**
     * @brief Whether the pencil decoration should render on the public CV.
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON payload.
     * @return bool True when enabled (default true when unset).
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function isEnabledFromPayload(array $payload): bool
    {
        if (!array_key_exists(self::KEY, $payload)) {
            return true;
        }

        return self::normalizeEnabled($payload[self::KEY][self::FIELD_ENABLED] ?? true);
    }

    /**
     * @brief Merge cv_data admin POST fields into a profile payload.
     *
     * @param array<string, mixed> $payload Existing decoded profile payload.
     * @param Request $request HTTP request with cv_data pencil fields.
     * @return array<string, mixed> Updated payload.
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function mergeSubmittedFromCvDataRequest(array $payload, Request $request): array
    {
        $existing = self::fromPayload($payload);
        $all = $request->request->all();

        $enabled = array_key_exists('cv_pencil_decoration_enabled', $all)
            ? self::normalizeEnabled($all['cv_pencil_decoration_enabled'])
            : $existing[self::FIELD_ENABLED];

        $lightToneMixPercent = $request->request->has('cv_pencil_light_tone_mix_percent')
            ? self::normalizeToneMixPercent($request->request->get('cv_pencil_light_tone_mix_percent'), $existing[self::FIELD_LIGHT_TONE_MIX_PERCENT])
            : $existing[self::FIELD_LIGHT_TONE_MIX_PERCENT];

        $darkToneMixPercent = $request->request->has('cv_pencil_dark_tone_mix_percent')
            ? self::normalizeToneMixPercent($request->request->get('cv_pencil_dark_tone_mix_percent'), $existing[self::FIELD_DARK_TONE_MIX_PERCENT])
            : $existing[self::FIELD_DARK_TONE_MIX_PERCENT];

        $payload[self::KEY] = [
            self::FIELD_ENABLED => $enabled,
            self::FIELD_LIGHT_TONE_MIX_PERCENT => $lightToneMixPercent,
            self::FIELD_DARK_TONE_MIX_PERCENT => $darkToneMixPercent,
        ];

        return $payload;
    }

    /**
     * @brief Default pencil decoration map.
     *
     * @param void No input parameter.
     * @return array{
     *     enabled: bool,
     *     lightToneMixPercent: int,
     *     darkToneMixPercent: int
     * }
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function defaultMap(): array
    {
        return [
            self::FIELD_ENABLED => true,
            self::FIELD_LIGHT_TONE_MIX_PERCENT => self::DEFAULT_LIGHT_TONE_MIX_PERCENT,
            self::FIELD_DARK_TONE_MIX_PERCENT => self::DEFAULT_DARK_TONE_MIX_PERCENT,
        ];
    }

    /**
     * @brief Normalize checkbox / switch enabled value.
     *
     * @param mixed $raw Raw submitted value.
     * @return bool True when enabled.
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function normalizeEnabled(mixed $raw): bool
    {
        if (is_array($raw)) {
            foreach ($raw as $value) {
                if (self::normalizeEnabled($value)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            return (int) $raw === 1;
        }

        if (!is_string($raw)) {
            return false;
        }

        $normalized = strtolower(trim($raw));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @brief Clamp white-mix percent for pencil gray tones.
     *
     * @param mixed $raw Raw submitted value.
     * @param int $fallback Fallback when raw is not numeric.
     * @return int Clamped integer percent between 0 and 100.
     * @date 2026-06-08
     * @author Stephane H.
     */
    public static function normalizeToneMixPercent(mixed $raw, int $fallback): int
    {
        if (!is_numeric($raw)) {
            return max(self::MIN_TONE_MIX_PERCENT, min(self::MAX_TONE_MIX_PERCENT, $fallback));
        }

        return max(
            self::MIN_TONE_MIX_PERCENT,
            min(self::MAX_TONE_MIX_PERCENT, (int) round((float) $raw))
        );
    }
}
