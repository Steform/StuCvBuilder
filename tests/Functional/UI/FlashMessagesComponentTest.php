<?php

namespace App\Tests\Functional\UI;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

class FlashMessagesComponentTest extends TestCase
{
    /**
     * @brief Ensure flash component maps flash types to Bootstrap toast backgrounds.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-29
     * @author Stephane H.
     */
    public function testFlashComponentRendersBootstrapClasses(): void
    {
        $root = dirname(__DIR__, 3);
        $twig = new Environment(new ArrayLoader([
            'components/_flash_messages.html.twig' => file_get_contents($root.'/templates/components/_flash_messages.html.twig') ?: '',
            'test.twig' => "{% include 'components/_flash_messages.html.twig' with {flashMessages: flashMessages} %}",
        ]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $value): string => $value));

        $rendered = $twig->render('test.twig', [
            'flashMessages' => [
                'success' => ['setup.totp_sent'],
                'error' => ['setup.invalid_payload'],
            ],
        ]);

        self::assertStringContainsString('toast-container', $rendered);
        self::assertStringContainsString('text-bg-success', $rendered);
        self::assertStringContainsString('text-bg-danger', $rendered);
        self::assertStringContainsString('data-flash-toast', $rendered);
        self::assertStringContainsString("data-bs-delay=\"8000\"", $rendered);
        self::assertStringContainsString('aria-atomic="true"', $rendered);
        self::assertStringContainsString("aria-label=\"app.close\"", $rendered);
        self::assertStringContainsString('flash-toast-content', $rendered);
        self::assertStringContainsString('flash-toast-body', $rendered);
        self::assertStringContainsString('flash-toast-close', $rendered);
        self::assertStringContainsString('ms-auto', $rendered);
        self::assertStringContainsString('flex-shrink-0', $rendered);
    }

    /**
     * @brief Ensure flash messages with translation parameters are passed to the trans filter.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testFlashComponentPassesTranslationParameters(): void
    {
        $root = dirname(__DIR__, 3);
        $twig = new Environment(new ArrayLoader([
            'components/_flash_messages.html.twig' => file_get_contents($root.'/templates/components/_flash_messages.html.twig') ?: '',
            'test.twig' => "{% include 'components/_flash_messages.html.twig' with {flashMessages: flashMessages} %}",
        ]));
        $twig->addGlobal('app', (object) [
            'request' => (object) ['locale' => 'fr'],
        ]);
        $twig->addFilter(new TwigFilter('trans', static function (mixed $value, array $params = [], mixed ...$rest): string {
            if (!is_string($value)) {
                return '';
            }

            if (!is_array($params)) {
                $params = [];
            }

            foreach ($params as $key => $replacement) {
                $value = str_replace((string) $key, (string) $replacement, $value);
            }

            return $value;
        }));

        $rendered = $twig->render('test.twig', [
            'flashMessages' => [
                'info' => [[
                    'message' => 'Backup saved as %filename%',
                    'parameters' => ['%filename%' => 'pre-reset-20260519.cvbackup'],
                ]],
            ],
        ]);

        self::assertStringContainsString('pre-reset-20260519.cvbackup', $rendered);
    }

    /**
     * @brief Ensure flash toast container z-index stays above floating bubbles.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-29
     * @author Stephane H.
     */
    public function testFlashToastCssUsesHighZIndex(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root.'/public/css/flash-toasts.css') ?: '';

        self::assertStringContainsString('z-index: 1200;', $source);
        self::assertStringContainsString('.flash-toast-close', $source);
        self::assertStringContainsString('align-self: flex-start;', $source);
    }

    /**
     * @brief Ensure runtime toast markup stays aligned with Twig toast markup.
     * @param void No input parameter.
     * @return void
     * @date 2026-05-03
     * @author Stephane H.
     */
    public function testRuntimeToastMarkupUsesRightAlignedCloseButtonClasses(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root.'/public/js/flash-toasts.js') ?: '';

        self::assertStringContainsString('flash-toast-content', $source);
        self::assertStringContainsString('flash-toast-body', $source);
        self::assertStringContainsString('flash-toast-close', $source);
        self::assertStringContainsString('ms-auto', $source);
        self::assertStringContainsString('flex-shrink-0', $source);
    }

    /**
     * @brief CKEditor init must wire cv_data tagline locale tabs, not only About presentation.
     *
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testCkeditorInitWiresCvDataTaglineLocaleTabs(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root.'/public/js/ckeditor-init.js') ?: '';

        self::assertStringContainsString('cvDataLocaleTabs', $source);
        self::assertStringContainsString('initCvDataLocaleEditors', $source);
        self::assertStringNotContainsString('if (initAboutPresentationLocaleEditors()) {\n      return;', $source);
    }
}
