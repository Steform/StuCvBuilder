<?php

declare(strict_types=1);

namespace App\Service\Uuid;

use Symfony\Component\Uid\Uuid;

/**
 * @brief Deterministic RFC 4122 UUID v5 generation for CV placeholder rows.
 */
final class DeterministicUuidFactory
{
    /** Application-specific namespace UUID for StuCvBuilder deterministic IDs. */
    private const APP_NAMESPACE = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

    /**
     * @brief Build a stable UUID from a domain label and seed string.
     *
     * @param string $domain Logical domain prefix (e.g. cv-education).
     * @param string $seed Deterministic seed value.
     * @return string UUID string.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function generate(string $domain, string $seed): string
    {
        return (string) Uuid::v5(
            Uuid::fromString(self::APP_NAMESPACE),
            $domain.'|'.$seed
        );
    }
}
