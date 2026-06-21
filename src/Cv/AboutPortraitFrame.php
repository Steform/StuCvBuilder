<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Allowed About section portrait frame styles stored as {@see CvProfile} JSON `aboutPortraitFrame`.
 */
enum AboutPortraitFrame: string
{
    case LegacyHalo = 'legacy_halo';
    case EditorialRing = 'editorial_ring';
    case Squircle = 'squircle';
    case GlassRim = 'glass_rim';

    /**
     * @brief Map stored JSON value to enum with safe default for missing or invalid input.
     * @param mixed $raw Raw payload value.
     * @return self
     * @date 2026-05-14
     * @author Stephane H.
     */
    public static function fromStored(mixed $raw): self
    {
        if (!is_string($raw) || $raw === '') {
            return self::LegacyHalo;
        }

        return self::tryFrom($raw) ?? self::LegacyHalo;
    }
}
