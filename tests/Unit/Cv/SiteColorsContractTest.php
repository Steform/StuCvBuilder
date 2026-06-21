<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\SiteColorsContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see SiteColorsContract}.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class SiteColorsContractTest extends TestCase
{
    /**
     * @brief Site accent must override profile About base color when set.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testResolveAccentPrefersSiteColorOverProfileFallback(): void
    {
        $accent = SiteColorsContract::resolveAccent(
            ['accent' => '#aabbcc', 'cvMenuBackground' => null],
            '#112233'
        );

        self::assertSame('#aabbcc', $accent);
    }

    /**
     * @brief Default site accent is used when site and profile colors are unset.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveAccentFallsBackToDefaultSiteAccent(): void
    {
        $accent = SiteColorsContract::resolveAccent(['accent' => null, 'cvMenuBackground' => null], null);

        self::assertSame(SiteColorsContract::DEFAULT_ACCENT_HEX, $accent);
    }

    /**
     * @brief Profile About base color remains fallback when site accent is unset.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testResolveAccentFallsBackToProfileColor(): void
    {
        $accent = SiteColorsContract::resolveAccent(['accent' => null, 'cvMenuBackground' => null], '#112233');

        self::assertSame('#112233', $accent);
    }

    /**
     * @brief Pattern CSS cache suffix must change when site accent changes.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testPatternCssCacheSuffixIncludesSiteAccent(): void
    {
        $payload = [
            AboutSectionPatternCustomizationContract::KEY => [
                'baseColor' => '#112233',
                'patternLeftId' => '',
                'patternRightId' => '',
                'toneMixPercent' => AboutSectionPatternCustomizationContract::DEFAULT_TONE_MIX_PERCENT,
                'surfaceMixPercent' => AboutSectionPatternCustomizationContract::DEFAULT_SURFACE_MIX_PERCENT,
                'darkSurfaceDarkenPercent' => AboutSectionPatternCustomizationContract::DEFAULT_DARK_SURFACE_DARKEN_PERCENT,
            ],
        ];

        $withoutSiteAccent = SiteColorsContract::patternCssCacheSuffix(['accent' => null, 'cvMenuBackground' => null], $payload);
        $withSiteAccent = SiteColorsContract::patternCssCacheSuffix(['accent' => '#aabbcc', 'cvMenuBackground' => null], $payload);

        self::assertNotSame($withoutSiteAccent, $withSiteAccent);
    }

    /**
     * @brief CV menu background falls back to default navy when unset.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testResolveCvMenuBackgroundUsesDefaultWhenUnset(): void
    {
        $background = SiteColorsContract::resolveCvMenuBackground(['accent' => null, 'cvMenuBackground' => null]);

        self::assertSame(SiteColorsContract::DEFAULT_CV_MENU_BACKGROUND_HEX, $background);
    }

    /**
     * @brief Layout CSS cache suffix must change when CV menu background changes.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testLayoutCssCacheSuffixIncludesCvMenuBackground(): void
    {
        $defaultSuffix = SiteColorsContract::layoutCssCacheSuffix(['accent' => null, 'cvMenuBackground' => null]);
        $customSuffix = SiteColorsContract::layoutCssCacheSuffix(['accent' => null, 'cvMenuBackground' => '#334455']);

        self::assertNotSame($defaultSuffix, $customSuffix);
    }
}
