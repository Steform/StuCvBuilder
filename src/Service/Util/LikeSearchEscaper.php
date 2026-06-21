<?php

declare(strict_types=1);

namespace App\Service\Util;

/**
 * @brief Escape SQL LIKE wildcard characters in user-provided search terms.
 */
final class LikeSearchEscaper
{
    /**
     * @brief Escape `%`, `_` and backslashes for LIKE comparisons.
     *
     * @param string $term Raw search term from user input.
     * @return string Escaped term safe inside `%...%` LIKE patterns.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
