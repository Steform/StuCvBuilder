<?php

declare(strict_types=1);

namespace App\Tests\Functional\Cv;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @brief Contract checks for dynamic About disk CSS variables.
 * @date 2026-05-09
 * @author Stephane H.
 */
final class CvAboutProfileCssVariablesContractTest extends WebTestCase
{
    /**
     * @brief Dynamic CSS endpoint must expose disk customization variables.
     * @return void
     * @date 2026-05-09
     * @author Stephane H.
     */
    public function testDynamicCssContainsDiskVariableContract(): void
    {
        $client = static::createClient();
        $client->request('GET', '/css/cv-about-profile.css');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/css; charset=UTF-8');

        $css = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('--about-disk-color-inner-rgba', $css);
        self::assertStringContainsString('--about-portrait-inner-rgba', $css);
        self::assertStringContainsString('var(--about-bg-secondary)', $css);
        self::assertStringContainsString('--about-disk-color-outer-rgba', $css);
        self::assertStringContainsString('--about-disk-border-rgba', $css);
        self::assertStringContainsString('--about-disk-glow-outer-rgba', $css);
        self::assertStringContainsString('--about-disk-glow-inner-rgba', $css);
        self::assertStringContainsString('--about-disk-glow-outer-blur', $css);
        self::assertStringContainsString('--about-disk-glow-inner-blur', $css);
        self::assertStringContainsString('--about-bg-decor-dots-size', $css);
        self::assertStringContainsString('--about-bg-decor-dots-dot-size', $css);
        self::assertStringContainsString('--about-bg-decor-enabled', $css);
        self::assertStringContainsString('--about-bg-decor-line-rgba', $css);

        $staticCss = @file_get_contents(dirname(__DIR__, 3).'/public/css/cv-about.css') ?: '';
        self::assertStringContainsString('--about-bg-decor-tint-rgba', $css);
        self::assertStringContainsString('--about-bg-decor-line-rgba', $css);
        self::assertStringContainsString('--about-bg-decor-intensity', $css);
        self::assertStringContainsString('--about-code-speed-factor', $css);
        self::assertStringContainsString('var(--about-particles-speed-factor', $staticCss);
        self::assertStringContainsString('var(--about-bg-decor-tint-rgba', $staticCss);
        self::assertStringContainsString('--about-bg-decor-fine-grid-step', $staticCss);
        self::assertStringContainsString('--bg-decor-hex_zoom_mesh', $staticCss);
        self::assertStringContainsString('--bg-decor-dev_code_rain', $staticCss);
        self::assertStringNotContainsString('.cv-about-presentation', $css);
        self::assertStringContainsString('.cv-about-profile-wrap', $css);
        self::assertStringNotContainsString('background: #f1f5f9', $css);
    }

    /**
     * @brief Atmosphere variables must be defined globally, not only inside the desktop media query.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testAtmosphereVariablesAreOutsideDesktopMediaQuery(): void
    {
        $client = static::createClient();
        $client->request('GET', '/css/cv-about-profile.css');

        self::assertResponseIsSuccessful();

        $css = (string) $client->getResponse()->getContent();
        $globalCss = $this->extractCssBeforeFirstDesktopMediaQuery($css);

        self::assertStringContainsString('--about-bg-primary:', $globalCss);
        self::assertStringContainsString('--about-bg-secondary:', $globalCss);
        self::assertStringContainsString('--about-halo-strength:', $globalCss);
        self::assertStringNotContainsString('--about-disk-enabled', $globalCss);
        self::assertStringContainsString('.cv-custom-section--about', $css);
        self::assertStringContainsString('--cv-about-pres-size-p:', $css);
    }

    /**
     * @brief Return CSS emitted before the first desktop-only `@media (min-width: 992px)` block.
     *
     * @param string $css Full generated stylesheet.
     * @return string Prefix of the stylesheet (global atmosphere rules).
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function extractCssBeforeFirstDesktopMediaQuery(string $css): string
    {
        $position = strpos($css, '@media (min-width: 992px)');

        return $position === false ? $css : substr($css, 0, $position);
    }
}

