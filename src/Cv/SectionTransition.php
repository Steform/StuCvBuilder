<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Allowed section exit transitions stored in {@see CvProfile} JSON `sectionTransitions`.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
enum SectionTransition: string
{
    case FadeVertical = 'fade_vertical';
    case None = 'none';
    case FadeShort = 'fade_short';
    case FadeStrong = 'fade_strong';
    case BridgeBand = 'bridge_band';
    case OverlapSoft = 'overlap_soft';

    /**
     * @brief Map stored JSON value to enum with safe default.
     *
     * @param mixed $raw Raw transition slug.
     * @return self Resolved transition case.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function fromStored(mixed $raw): self
    {
        if (is_string($raw) && $raw !== '') {
            $resolved = self::tryFrom($raw);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return self::FadeVertical;
    }

    /**
     * @brief Stored string values safe for public BEM class suffixes.
     *
     * @return list<string>
     * @date 2026-05-20
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
     * @brief Cases exposed in admin transition picker.
     *
     * @return list<self>
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function casesForAdmin(): array
    {
        return self::cases();
    }
}
