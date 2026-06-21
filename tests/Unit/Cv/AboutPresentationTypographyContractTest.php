<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\AboutPresentationTypographyContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see AboutPresentationTypographyContract}.
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
final class AboutPresentationTypographyContractTest extends TestCase
{
    /**
     * @brief Invalid units or values must fall back to defaults.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testNormalizeRejectsInvalidFontSizes(): void
    {
        $normalized = AboutPresentationTypographyContract::normalize([
            AboutPresentationTypographyContract::ELEMENT_H1 => [
                'value' => '99',
                'unit' => 'pt',
            ],
            AboutPresentationTypographyContract::ELEMENT_P => [
                'value' => '-1',
                'unit' => 'rem',
            ],
        ]);

        self::assertSame('2.25rem', $normalized[AboutPresentationTypographyContract::ELEMENT_H1]);
        self::assertSame('1rem', $normalized[AboutPresentationTypographyContract::ELEMENT_P]);
    }

    /**
     * @brief mergeSubmittedIntoPayload must persist valid custom sizes.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    /**
     * @brief Value field may contain a combined CSS size such as `4rem`.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testSanitizeElementAcceptsCombinedValueInValueField(): void
    {
        $payload = AboutPresentationTypographyContract::mergeSubmittedIntoPayload([], [
            AboutPresentationTypographyContract::ELEMENT_H1 => [
                'value' => '4rem',
                'unit' => 'rem',
            ],
        ]);

        $normalized = AboutPresentationTypographyContract::fromPayload($payload);
        self::assertSame('4rem', $normalized[AboutPresentationTypographyContract::ELEMENT_H1]);
    }

    public function testMergeSubmittedIntoPayloadStoresValidSizes(): void
    {
        $payload = AboutPresentationTypographyContract::mergeSubmittedIntoPayload([], [
            AboutPresentationTypographyContract::ELEMENT_H2 => [
                'value' => '1.5',
                'unit' => 'em',
            ],
        ]);

        $stored = $payload[AboutPresentationTypographyContract::KEY];
        self::assertIsArray($stored);
        self::assertSame(
            ['value' => '1.5', 'unit' => 'em'],
            $stored[AboutPresentationTypographyContract::ELEMENT_H2]
        );

        $normalized = AboutPresentationTypographyContract::fromPayload($payload);
        self::assertSame('1.5em', $normalized[AboutPresentationTypographyContract::ELEMENT_H2]);
    }
}
