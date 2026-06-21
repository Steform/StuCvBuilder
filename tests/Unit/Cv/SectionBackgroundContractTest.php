<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\SectionBackgroundContract;
use App\Cv\SectionTransitionContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for section background contract and normalization.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
final class SectionBackgroundContractTest extends TestCase
{
    /**
     * @brief Legacy texture keys must hydrate sectionBackgrounds on normalize.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testNormalizeMapMigratesLegacyTextureKeys(): void
    {
        $payload = [
            'situationBackgroundTexture' => 'texture_3',
            'experienceBackgroundTexture' => 'texture_5',
        ];

        $map = SectionBackgroundContract::normalizeMap(null, $payload);

        self::assertSame('texture_3', $map['situation']['texture']);
        self::assertSame('texture_5', $map['experience']['texture']);
        self::assertSame('texture_1', $map['skills']['texture']);
    }

    /**
     * @brief Custom color mode requires valid hex pair.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testNormalizeBlockFallsBackWhenCustomColorsInvalid(): void
    {
        $block = SectionBackgroundContract::normalizeBlock([
            'colorMode' => 'custom',
            'primary' => '#xyz',
            'secondary' => '#03215a',
        ]);

        self::assertSame(SectionBackgroundContract::COLOR_MODE_ABOUT, $block['colorMode']);
    }

    /**
     * @brief Intensity values must clamp to allowed range.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testNormalizeIntensityClamps(): void
    {
        self::assertSame(0.05, SectionBackgroundContract::normalizeIntensity(0.01, 0.22));
        self::assertSame(0.95, SectionBackgroundContract::normalizeIntensity(2.0, 0.32));
        self::assertSame(0.22, SectionBackgroundContract::normalizeIntensity(null, 0.22));
    }

    /**
     * @brief High intensity must increase legacy gradient overlay alpha.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testIntensityToLegacyOverlayAlphaScalesWithSlider(): void
    {
        $low = SectionBackgroundContract::intensityToLegacyOverlayAlpha(0.22, false);
        $high = SectionBackgroundContract::intensityToLegacyOverlayAlpha(0.95, false);

        self::assertGreaterThan($low, $high);
        self::assertSame(0.65, $low);
    }

    /**
     * @brief Legacy texture vars must emit rgba overlay from secondary hex.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testIntensityToLegacyTextureVarsBuildsRgbaOverlay(): void
    {
        $vars = SectionBackgroundContract::intensityToLegacyTextureVars(0.22, '#03a0d7', false);

        self::assertStringStartsWith('rgba(3, 160, 215, ', $vars['overlayRgba']);
        self::assertStringContainsString('0.65', $vars['overlayRgba']);
    }

    /**
     * @brief mergeSubmittedSectionIntoPayload must sync legacy keys.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testMergeSubmittedSectionSyncsLegacyKeys(): void
    {
        $payload = SectionBackgroundContract::mergeSubmittedSectionIntoPayload(
            [],
            'skills',
            [
                'texture' => 'texture_2',
                'colorMode' => 'about',
                'filterIntensityLight' => '0.4',
                'filterIntensityDark' => '0.5',
            ]
        );

        self::assertSame('texture_2', $payload[SectionBackgroundContract::KEY]['skills']['texture']);
        self::assertCount(count(SectionTransitionContract::ELIGIBLE_SECTION_KEYS), $payload[SectionBackgroundContract::KEY]);
    }

    /**
     * @brief About tone adjust percent must clamp to -100..100.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testNormalizeAboutColorAdjustPercentClamps(): void
    {
        self::assertSame(0, SectionBackgroundContract::normalizeAboutColorAdjustPercent(null));
        self::assertSame(-100, SectionBackgroundContract::normalizeAboutColorAdjustPercent(-250));
        self::assertSame(100, SectionBackgroundContract::normalizeAboutColorAdjustPercent(250));
        self::assertSame(76, SectionBackgroundContract::normalizeAboutColorAdjustPercent('75.6'));
        self::assertSame(-40, SectionBackgroundContract::normalizeAboutColorAdjustPercent('-40'));
    }

    /**
     * @brief adjustHexByAboutTone must lighten or darken relative to neutral.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testAdjustHexByAboutToneLightensAndDarkens(): void
    {
        $base = '#03215a';

        self::assertSame($base, SectionBackgroundContract::adjustHexByAboutTone($base, 0));

        $lighter = SectionBackgroundContract::adjustHexByAboutTone($base, 50);
        $darker = SectionBackgroundContract::adjustHexByAboutTone($base, -50);

        self::assertNotSame($base, $lighter);
        self::assertNotSame($base, $darker);
        self::assertGreaterThan(
            SectionBackgroundContract::hexToRgb($darker)['r'],
            SectionBackgroundContract::hexToRgb($lighter)['r']
        );
    }

    /**
     * @brief Partial experience background submit must preserve texture while updating tone.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testMergeSubmittedExperiencePreservesTextureWhenAdjustingTone(): void
    {
        $payload = [
            SectionBackgroundContract::KEY => [
                'experience' => [
                    'texture' => 'texture_4',
                    'colorMode' => 'about',
                    'aboutColorAdjustPercent' => 0,
                ],
            ],
        ];

        $merged = SectionBackgroundContract::mergeSubmittedSectionIntoPayload(
            $payload,
            'experience',
            [
                'colorMode' => 'about',
                'aboutColorAdjustPercent' => '25',
            ]
        );

        self::assertSame('texture_4', $merged[SectionBackgroundContract::KEY]['experience']['texture']);
        self::assertSame(25, $merged[SectionBackgroundContract::KEY]['experience']['aboutColorAdjustPercent']);
    }
}
