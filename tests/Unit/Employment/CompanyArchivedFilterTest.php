<?php

declare(strict_types=1);

namespace App\Tests\Unit\Employment;

use App\Employment\CompanyArchivedFilter;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for company archived filter normalization.
 */
final class CompanyArchivedFilterTest extends TestCase
{
    /**
     * @brief Known filter values are preserved.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testNormalizePreservesKnownValues(): void
    {
        self::assertSame(CompanyArchivedFilter::ACTIVE, CompanyArchivedFilter::normalize('active'));
        self::assertSame(CompanyArchivedFilter::ARCHIVED, CompanyArchivedFilter::normalize('archived'));
        self::assertSame(CompanyArchivedFilter::ALL, CompanyArchivedFilter::normalize('all'));
    }

    /**
     * @brief Legacy include-archived checkbox maps to all.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testNormalizeLegacyIncludeArchivedMapsToAll(): void
    {
        self::assertSame(CompanyArchivedFilter::ALL, CompanyArchivedFilter::normalize(null, true));
        self::assertSame(CompanyArchivedFilter::ACTIVE, CompanyArchivedFilter::normalize(null, false));
    }
}
