<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Cv\AboutPresentationTypographyContract;
use App\Service\Cv\CvAboutPresentationTypographyCssBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvAboutPresentationTypographyCssBuilder}.
 *
 * @date 2026-05-23
 * @author Stephane H.
 */
final class CvAboutPresentationTypographyCssBuilderTest extends TestCase
{
    /**
     * @brief CSS must scope rules to `.cv-about__presentation` only.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testBuildCssScopesPresentationBlockOnly(): void
    {
        $css = (new CvAboutPresentationTypographyCssBuilder())->buildCss(
            AboutPresentationTypographyContract::normalize([
                AboutPresentationTypographyContract::ELEMENT_H1 => [
                    'value' => '3',
                    'unit' => 'rem',
                ],
            ])
        );

        self::assertStringContainsString('--cv-about-pres-size-h1: 3rem;', $css);
        self::assertStringContainsString('.cv-about__presentation h1 {', $css);
        self::assertStringContainsString('.cv-about__presentation p {', $css);
        self::assertStringNotContainsString('.cv-about-presentation', $css);
    }
}
