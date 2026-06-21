<?php

declare(strict_types=1);

namespace App\Employment;

/**
 * CV connection log classification values.
 */
final class ConnectionKind
{
    public const RANDOM = 'random';

    public const TECHNICAL_INVALID_FORMAT = 'technical_invalid_format';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::RANDOM,
            self::TECHNICAL_INVALID_FORMAT,
        ];
    }
}
