<?php

declare(strict_types=1);

namespace App\Employment;

/**
 * Admin tracked company list archived visibility filter values.
 */
final class CompanyArchivedFilter
{
    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    public const ALL = 'all';

    /**
     * @brief Normalize archived filter query value.
     *
     * @param string|null $filter Raw query filter.
     * @param bool $legacyIncludeArchived Legacy `archived=1` checkbox compatibility.
     * @return string One of ACTIVE, ARCHIVED, or ALL.
     * @date 2026-06-17
     * @author Stephane H.
     */
    public static function normalize(?string $filter, bool $legacyIncludeArchived = false): string
    {
        if (is_string($filter) && in_array($filter, [self::ACTIVE, self::ARCHIVED, self::ALL], true)) {
            return $filter;
        }

        if ($legacyIncludeArchived) {
            return self::ALL;
        }

        return self::ACTIVE;
    }
}
