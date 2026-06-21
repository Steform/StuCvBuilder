<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\CvPencilDecorationContract;
use App\Service\Cv\CvAboutPatternCssBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvAboutPatternCssBuilder}.
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
final class CvAboutPatternCssBuilderTest extends TestCase
{
    public function testBuildCssEmitsPatternVariables(): void
    {
        $css = (new CvAboutPatternCssBuilder())->buildCss([
            'baseColor' => '#2563eb',
            'toneMixPercent' => [
                'tone1' => 0,
                'tone2' => 10,
                'tone3' => 35,
                'tone4' => 60,
            ],
            'surfaceMixPercent' => 75,
            'darkSurfaceDarkenPercent' => 22,
        ], [], '#aabbcc');

        self::assertStringContainsString('.cv-public-page,', $css);
        self::assertStringContainsString('.cv-skills,', $css);
        self::assertStringContainsString('.cv-custom-section--situation {', $css);
        self::assertStringContainsString('--cv-about-pattern-base: #2563eb;', $css);
        self::assertStringContainsString('--cv-about-accent: #aabbcc;', $css);
        self::assertStringContainsString('--cv-about-pattern-mix-1: 0%;', $css);
        self::assertStringContainsString('--cv-about-pattern-mix-4: 60%;', $css);
        self::assertStringContainsString('--cv-about-pattern-surface-mix: 75%;', $css);
        self::assertStringContainsString('--cv-about-surface-darken-mix: 22%;', $css);
    }

    public function testBuildCssUsesSiteAccentForPencilWhilePatternBaseStaysNeutral(): void
    {
        $css = (new CvAboutPatternCssBuilder())->buildCss(
            [
                'baseColor' => '#2d2d2d',
                'toneMixPercent' => AboutSectionPatternCustomizationContract::DEFAULT_TONE_MIX_PERCENT,
            ],
            [],
            '#1e5a96'
        );

        self::assertStringContainsString('--cv-about-pattern-base: #2d2d2d;', $css);
        self::assertStringContainsString('--cv-about-accent: #1e5a96;', $css);
        self::assertStringContainsString('#1e5a96 calc(100% - 93%)', $css);
    }

    public function testBuildCssEmitsPencilToneVariablesFromPayload(): void
    {
        $css = (new CvAboutPatternCssBuilder())->buildCss(
            [
                'baseColor' => '#2d2d2d',
                'toneMixPercent' => AboutSectionPatternCustomizationContract::DEFAULT_TONE_MIX_PERCENT,
            ],
            [
                CvPencilDecorationContract::KEY => [
                    'enabled' => true,
                    'lightToneMixPercent' => 80,
                    'darkToneMixPercent' => 70,
                ],
            ],
            '#2563eb'
        );

        self::assertStringContainsString('.cv-pencil-decor {', $css);
        self::assertStringContainsString('--cv-pencil-tone-light:', $css);
        self::assertStringContainsString('#2563eb calc(100% - 80%)', $css);
        self::assertStringContainsString('--cv-pencil-tone-dark:', $css);
        self::assertStringContainsString('#2563eb calc(100% - 70%)', $css);
    }

    public function testBuildCssUsesDefaultPencilToneMixWhenPayloadIsEmpty(): void
    {
        $css = (new CvAboutPatternCssBuilder())->buildCss([
            'baseColor' => '#2d2d2d',
            'toneMixPercent' => AboutSectionPatternCustomizationContract::DEFAULT_TONE_MIX_PERCENT,
        ], [], '#2563eb');

        self::assertStringContainsString('white 93%', $css);
        self::assertStringContainsString('white 90%', $css);
        self::assertStringContainsString('#2563eb calc(100% - 93%)', $css);
    }
}
