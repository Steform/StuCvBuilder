<?php

declare(strict_types=1);

namespace App\Employment;

/**
 * @brief How a tracked company resolves public CV content relative to the global profile.
 */
final class CompanyCvContentMode
{
    public const SYNCED = 'synced';

    public const CUSTOM = 'custom';

    /**
     * @brief List all supported content modes.
     *
     * @return list<string>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public static function all(): array
    {
        return [
            self::SYNCED,
            self::CUSTOM,
        ];
    }

    /**
     * @brief Whether the raw value is a known content mode.
     *
     * @param string $value Candidate mode.
     * @return bool
     * @date 2026-07-23
     * @author Stephane H.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::all(), true);
    }

    /**
     * @brief Normalize a raw mode or fall back to synced.
     *
     * @param string|null $value Candidate mode.
     * @return string One of {@see self::all()}.
     * @date 2026-07-23
     * @author Stephane H.
     */
    public static function normalize(?string $value): string
    {
        $trimmed = is_string($value) ? trim($value) : '';
        if (self::isValid($trimmed)) {
            return $trimmed;
        }

        return self::SYNCED;
    }
}
