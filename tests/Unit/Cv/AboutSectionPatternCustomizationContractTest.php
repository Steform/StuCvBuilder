<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\AboutSectionPatternCustomizationContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see AboutSectionPatternCustomizationContract}.
 *
 * @date 2026-05-28
 * @author Stephane H.
 */
final class AboutSectionPatternCustomizationContractTest extends TestCase
{
    public function testFromPayloadMigratesLegacyColorString(): void
    {
        $pattern = AboutSectionPatternCustomizationContract::fromPayload([
            AboutSectionPatternCustomizationContract::LEGACY_COLOR_KEY => '#336699',
        ]);

        self::assertSame('#336699', $pattern['baseColor']);
        self::assertSame(AboutSectionPatternCustomizationContract::DEFAULT_PATTERN_LEFT_ID, $pattern['patternId']);
        self::assertSame(AboutSectionPatternCustomizationContract::DEFAULT_PATTERN_LEFT_ID, $pattern['patternLeftId']);
        self::assertSame(AboutSectionPatternCustomizationContract::DEFAULT_PATTERN_RIGHT_ID, $pattern['patternRightId']);
        self::assertSame(0, $pattern['toneMixPercent']['left']['tone1']);
        self::assertSame(0, $pattern['toneMixPercent']['right']['tone1']);
    }

    public function testMergeSubmittedIntoPayloadStoresToneMix(): void
    {
        $payload = AboutSectionPatternCustomizationContract::mergeSubmittedIntoPayload(
            [],
            '#112233',
            [
                'left' => ['tone1' => 5, 'tone2' => 20, 'tone3' => 45, 'tone4' => 80],
                'right' => ['tone1' => 9, 'tone2' => 24, 'tone3' => 49, 'tone4' => 84],
            ]
        );

        $stored = $payload[AboutSectionPatternCustomizationContract::KEY];
        self::assertIsArray($stored);
        self::assertSame('#112233', $stored['baseColor']);
        self::assertSame(5, $stored['toneMixPercent']['left']['tone1']);
        self::assertSame(80, $stored['toneMixPercent']['left']['tone4']);
        self::assertSame(9, $stored['toneMixPercent']['right']['tone1']);
        self::assertSame(84, $stored['toneMixPercent']['right']['tone4']);
        self::assertArrayNotHasKey(AboutSectionPatternCustomizationContract::LEGACY_COLOR_KEY, $payload);
    }

    public function testNormalizeUsesToneFourWhenSurfaceMixMissing(): void
    {
        $pattern = AboutSectionPatternCustomizationContract::normalize([
            'baseColor' => '#112233',
            'toneMixPercent' => ['left' => ['tone4' => 55]],
        ]);

        self::assertSame(55, $pattern['surfaceMixPercent']);
    }

    /**
     * @brief Ensure dark-surface darkening falls back to the contract default when omitted.
     *
     * @return void
     * @date 2026-05-28
     * @author Stephane H.
     */
    public function testNormalizeUsesDefaultDarkSurfaceDarkenWhenMissing(): void
    {
        $pattern = AboutSectionPatternCustomizationContract::normalize([
            'baseColor' => '#112233',
        ]);

        self::assertSame(
            AboutSectionPatternCustomizationContract::DEFAULT_DARK_SURFACE_DARKEN_PERCENT,
            $pattern['darkSurfaceDarkenPercent']
        );
    }

    /**
     * @brief Ensure submitted surface and darkening percentages are persisted in payload.
     *
     * @return void
     * @date 2026-05-28
     * @author Stephane H.
     */
    public function testMergeSubmittedIntoPayloadStoresSurfaceMix(): void
    {
        $payload = AboutSectionPatternCustomizationContract::mergeSubmittedIntoPayload(
            [],
            '#112233',
            [
                'left' => ['tone1' => 0, 'tone2' => 10, 'tone3' => 30, 'tone4' => 50],
                'right' => ['tone1' => 2, 'tone2' => 12, 'tone3' => 32, 'tone4' => 52],
            ],
            88,
            27,
            'fond-about-left',
            'fond-about-right'
        );

        $stored = $payload[AboutSectionPatternCustomizationContract::KEY];
        self::assertIsArray($stored);
        self::assertSame(88, $stored['surfaceMixPercent']);
        self::assertSame(27, $stored['darkSurfaceDarkenPercent']);
        self::assertSame('fond-about-left', $stored['patternLeftId']);
        self::assertSame('fond-about-right', $stored['patternRightId']);
    }

    public function testNormalizeToneMixPercentClampsValues(): void
    {
        $mix = AboutSectionPatternCustomizationContract::normalizeToneMixPercentMap([
            'tone1' => -5,
            'tone4' => 150,
        ]);

        self::assertSame(0, $mix['tone1']);
        self::assertSame(100, $mix['tone4']);
    }

    public function testNormalizeToneMixPercentBySideFallbacksFromLegacyFlatMap(): void
    {
        $mix = AboutSectionPatternCustomizationContract::normalizeToneMixPercentBySideMap([
            'tone1' => 7,
            'tone4' => 93,
        ]);

        self::assertSame(7, $mix['left']['tone1']);
        self::assertSame(93, $mix['left']['tone4']);
        self::assertSame(7, $mix['right']['tone1']);
        self::assertSame(93, $mix['right']['tone4']);
    }
}
