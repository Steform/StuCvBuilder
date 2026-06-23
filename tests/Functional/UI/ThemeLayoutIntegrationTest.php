<?php

namespace App\Tests\Functional\UI;

use PHPUnit\Framework\TestCase;

class ThemeLayoutIntegrationTest extends TestCase
{
    /**
     * @brief Ensure base layout exposes theme class and floating actions include.
     * @return void
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function testBaseLayoutContainsThemeHooks(): void
    {
        $root = dirname(__DIR__, 3);
        $baseTemplate = file_get_contents($root.'/templates/base.html.twig') ?: '';

        self::assertStringContainsString("app.request.attributes.get('app_theme', 'light')", $baseTemplate);
        self::assertStringContainsString('data-bs-theme="{{ currentTheme }}"', $baseTemplate);
        self::assertStringContainsString("components/_floating_actions.html.twig", $baseTemplate);
        self::assertStringContainsString("css/floating-actions.css", $baseTemplate);
    }

    /**
     * @brief Ensure home landing layout remains independent from theme body class.
     * @return void
     * @date 2026-05-02
     * @author Stephane H.
     */
    public function testHomeLayoutBodyClassIsUnchanged(): void
    {
        $root = dirname(__DIR__, 3);
        $homeTemplate = file_get_contents($root.'/templates/layouts/home_landing.html.twig') ?: '';

        self::assertStringContainsString('home-landing-page {{ currentTheme == \'dark\' ? \'theme-dark\' : \'theme-light\' }}', $homeTemplate);
        self::assertStringContainsString('data-bs-theme="{{ currentTheme }}"', $homeTemplate);
        self::assertStringContainsString("components/_floating_actions.html.twig", $homeTemplate);
    }
}
