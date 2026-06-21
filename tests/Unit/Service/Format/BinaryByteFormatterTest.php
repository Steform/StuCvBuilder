<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Format;

use App\Service\Format\BinaryByteFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for BinaryByteFormatter (same rules as files_size_format).
 * @author Stephane H.
 * @date 2026-05-03
 */
final class BinaryByteFormatterTest extends TestCase
{
    private BinaryByteFormatter $formatter;

    /**
     * @return void
     * @author Stephane H.
     * @date 2026-05-03
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new BinaryByteFormatter();
    }

    /**
     * @return void
     * @author Stephane H.
     * @date 2026-05-03
     */
    public function testFormatMatchesExtensionGoldenSamples(): void
    {
        self::assertSame('0 o', $this->formatter->format(0));
        self::assertSame('1 o', $this->formatter->format(1));
        self::assertSame('1023 o', $this->formatter->format(1023));
        self::assertSame('1.00 Ko', $this->formatter->format(1024));
        self::assertSame('1.00 Mo', $this->formatter->format(1024 * 1024));
        self::assertSame('1.00 Go', $this->formatter->format(1024 * 1024 * 1024));
        $oneTib = 1024 * 1024 * 1024 * 1024;
        self::assertSame('1.00 To', $this->formatter->format($oneTib));
        $onePib = $oneTib * 1024;
        self::assertSame('1.00 Po', $this->formatter->format($onePib));
        self::assertSame('1024.00 Po', $this->formatter->format($onePib * 1024));
    }
}
