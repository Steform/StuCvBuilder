<?php

declare(strict_types=1);

namespace App\Tests\Unit\Employment;

use App\Employment\CompanyCvContentMode;
use PHPUnit\Framework\TestCase;

final class CompanyCvContentModeTest extends TestCase
{
    /**
     * @brief Unknown values fall back to synced.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testNormalizeFallsBackToSynced(): void
    {
        self::assertSame(CompanyCvContentMode::SYNCED, CompanyCvContentMode::normalize(null));
        self::assertSame(CompanyCvContentMode::SYNCED, CompanyCvContentMode::normalize('nope'));
        self::assertSame(CompanyCvContentMode::CUSTOM, CompanyCvContentMode::normalize('custom'));
    }
}
