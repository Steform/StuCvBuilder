<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Global public CV access mode stored on {@see HomeCustomization}.
 *
 * @date 2026-07-22
 * @author Stephane H.
 */
enum CvPublicAccessMode: string
{
    case Open = 'open';
    case Gated = 'gated';
    case FormatRequired = 'format_required';

    /**
     * @brief Resolve stored value with safe default preserving legacy behaviour.
     *
     * @param mixed $raw Raw persisted mode slug.
     * @return self Resolved access mode.
     * @date 2026-07-22
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

        return self::Gated;
    }
}
