<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CvAgeYearsCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvAgeYearsCalculator}.
 */
final class CvAgeYearsCalculatorTest extends TestCase
{
    private CvAgeYearsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CvAgeYearsCalculator();
    }

    /**
     * @brief Birthday not yet reached subtracts one year.
     *
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function testComputeFromBirthDateBeforeBirthdayThisYear(): void
    {
        $now = new \DateTimeImmutable('2026-06-15', new \DateTimeZone('UTC'));

        self::assertSame(39, $this->calculator->computeFromBirthDate('1986-12-14', $now));
    }

    /**
     * @brief Birthday already passed keeps full year span.
     *
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function testComputeFromBirthDateAfterBirthdayThisYear(): void
    {
        $now = new \DateTimeImmutable('2026-06-15', new \DateTimeZone('UTC'));

        self::assertSame(40, $this->calculator->computeFromBirthDate('1986-05-14', $now));
    }

    /**
     * @brief Invalid or missing birth date returns null.
     *
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function testComputeFromBirthDateInvalidReturnsNull(): void
    {
        self::assertNull($this->calculator->computeFromBirthDate(null));
        self::assertNull($this->calculator->computeFromBirthDate(''));
        self::assertNull($this->calculator->computeFromBirthDate('not-a-date'));
        self::assertNull($this->calculator->computeFromBirthDate('2030-01-01', new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC'))));
    }
}
