<?php

declare(strict_types=1);

namespace App\Employment;

/**
 * Printable employment document kind (CV or cover letter).
 */
final class EmploymentDocumentKind
{
    public const CV = 'cv';

    public const LM = 'lm';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CV,
            self::LM,
        ];
    }

    /**
     * @brief Return whether the kind value is supported.
     *
     * @param string $kind Kind slug.
     * @return bool
     * @date 2026-06-01
     * @author Stephane H.
     */
    public static function isValid(string $kind): bool
    {
        return in_array($kind, self::all(), true);
    }
}
