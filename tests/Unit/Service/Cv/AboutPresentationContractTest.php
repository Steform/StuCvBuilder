<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\AboutPresentationContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for About presentation HTML helpers and stylesheet cache suffix.
 * @date 2026-05-23
 * @author Stephane H.
 */
final class AboutPresentationContractTest extends TestCase
{
    /**
     * @brief Per-locale map is preferred over legacy scalar HTML.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testHtmlByLocaleFromStoredPayloadPrefersMapOverLegacyScalar(): void
    {
        $payload = [
            AboutPresentationContract::KEY_HTML => '<p>legacy</p>',
            AboutPresentationContract::KEY_HTML_BY_LOCALE => [
                'fr' => '<p>fr</p>',
                'en' => '<p>en</p>',
            ],
        ];

        $map = AboutPresentationContract::htmlByLocaleFromStoredPayload($payload, ['fr', 'en'], 'fr');

        self::assertSame('<p>fr</p>', $map['fr']);
        self::assertSame('<p>en</p>', $map['en']);
    }

    /**
     * @brief Legacy scalar migrates into the default locale when the map key is absent.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testHtmlByLocaleFromStoredPayloadMigratesLegacyScalar(): void
    {
        $payload = [
            AboutPresentationContract::KEY_HTML => '<p>legacy only</p>',
        ];

        $map = AboutPresentationContract::htmlByLocaleFromStoredPayload($payload, ['fr', 'en'], 'fr');

        self::assertSame('<p>legacy only</p>', $map['fr']);
        self::assertSame('', $map['en']);
    }

    /**
     * @brief Locale pick follows display → default → first non-empty active locale.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testPickPresentationHtmlForLocaleFallbackOrder(): void
    {
        $htmlByLocale = ['fr' => '', 'en' => '<p>en body</p>', 'de' => '<p>de body</p>'];

        self::assertSame('<p>en body</p>', AboutPresentationContract::pickPresentationHtmlForLocale(
            $htmlByLocale,
            'fr',
            'fr',
            ['fr', 'en', 'de']
        ));

        self::assertSame('<p>en body</p>', AboutPresentationContract::pickPresentationHtmlForLocale(
            $htmlByLocale,
            'lt',
            'fr',
            ['fr', 'en', 'de']
        ));
    }

    /**
     * @brief Stylesheet cache suffix changes when atmosphere changes and ignores deprecated layout or photo layout keys.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testStylesheetCacheSuffixFromPayloadUsesAtmosphereOnly(): void
    {
        $base = [
            'aboutSectionAtmosphereStyle' => 'editorial_soft',
            'aboutProfilePhotoXPercent' => 12.0,
            'aboutProfilePhotoWidthPx' => 280,
        ];

        $withLayout = $base + [
            AboutPresentationContract::KEY_LAYOUT_DESKTOP => ['leftValue' => 99.0],
            AboutPresentationContract::KEY_LAYOUT_MOBILE => ['leftValue' => 1.0],
        ];

        self::assertSame(
            AboutPresentationContract::stylesheetCacheSuffixFromPayload($base, 1),
            AboutPresentationContract::stylesheetCacheSuffixFromPayload($withLayout, 1)
        );

        $altered = $base;
        $altered['aboutSectionAtmosphereStyle'] = 'style_2';

        self::assertNotSame(
            AboutPresentationContract::stylesheetCacheSuffixFromPayload($base, 1),
            AboutPresentationContract::stylesheetCacheSuffixFromPayload($altered, 1)
        );
    }
}
