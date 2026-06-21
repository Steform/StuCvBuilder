<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Allowed About section background decoration styles stored as {@see CvProfile} JSON `aboutBackgroundDecoration`.
 */
enum AboutBackgroundDecoration: string
{
    case None = 'none';
    case DotGrid = 'dot_grid';
    case DiagonalHatch = 'diagonal_hatch';
    case HexZoomMesh = 'hex_zoom_mesh';
    case AmbientParticles = 'ambient_particles';
    case IsometricGrid = 'isometric_grid';
    case FineGrid = 'fine_grid';
    case DevCodeRain = 'dev_code_rain';

    /**
     * @brief Map stored JSON value to enum with legacy migration from aboutDots* keys and removed styles.
     *
     * @param mixed $raw Raw `aboutBackgroundDecoration` value.
     * @param array<string, mixed> $payload Full decoded profile payload for legacy inference.
     * @return self
     * @date 2026-05-18
     * @author Stephane H.
     */
    public static function fromStored(mixed $raw, array $payload = []): self
    {
        if (is_string($raw) && $raw !== '') {
            $resolved = match ($raw) {
                'vignette', 'directional_glow', 'depth_fade' => self::HexZoomMesh,
                'film_grain', 'paper_texture', 'subtle_mesh' => self::AmbientParticles,
                'horizontal_sweep', 'accent_guides' => self::IsometricGrid,
                default => self::tryFrom($raw),
            };
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $rawEnabled = $payload['aboutDotsEnabled'] ?? null;
        if ($rawEnabled !== null) {
            $enabled = is_bool($rawEnabled)
                ? $rawEnabled
                : filter_var($rawEnabled, FILTER_VALIDATE_BOOLEAN);

            return $enabled ? self::DotGrid : self::None;
        }

        foreach (array_keys($payload) as $key) {
            if (is_string($key) && str_starts_with($key, 'aboutDots')) {
                return self::DotGrid;
            }
        }

        return self::DotGrid;
    }

    /**
     * @brief Stored string values safe for public BEM class suffixes.
     *
     * @return list<string>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public static function storedValues(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }

    /**
     * @brief Cases exposed in admin radio list (none + decorative styles).
     *
     * @return list<self>
     * @date 2026-05-18
     * @author Stephane H.
     */
    public static function casesForAdmin(): array
    {
        return [
            self::None,
            self::DotGrid,
            self::DiagonalHatch,
            self::HexZoomMesh,
            self::AmbientParticles,
            self::IsometricGrid,
            self::FineGrid,
            self::DevCodeRain,
        ];
    }
}
