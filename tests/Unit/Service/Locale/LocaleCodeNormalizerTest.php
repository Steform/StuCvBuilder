<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Locale;

use App\Service\Locale\LocaleCodeNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for canonical bokmål locale normalization.
 *
 * @date 2026-07-05
 * @author Stephane H.
 */
final class LocaleCodeNormalizerTest extends TestCase
{
    private LocaleCodeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new LocaleCodeNormalizer();
    }

    /**
     * @brief Browser bokmål variants must resolve to canonical nb.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testBrowserBokmalVariantsResolveToNb(): void
    {
        self::assertSame('nb', $this->normalizer->normalizeRawCode('nb-NO'));
        self::assertSame('nb', $this->normalizer->normalizeRawCode('nb'));
    }

    /**
     * @brief Legacy site locale no must resolve to canonical nb.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testLegacyNoResolvesToNb(): void
    {
        self::assertSame('nb', $this->normalizer->normalizeRawCode('no'));
        self::assertSame('nb', $this->normalizer->normalizeRawCode('no_NO'));
    }

    /**
     * @brief Unsupported nynorsk browser hint keeps current product fallback to bokmål UI.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testNynorskBrowserHintFallsBackToNb(): void
    {
        self::assertSame('nb', $this->normalizer->normalizeRawCode('nn'));
    }

    /**
     * @brief Supported list must accept canonical nb and reject unknown locales.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testNormalizeToSupportedReturnsCanonicalNb(): void
    {
        $supported = ['fr', 'en', 'de', 'lt', 'nb'];

        self::assertSame('nb', $this->normalizer->normalizeToSupported('no', $supported));
        self::assertSame('nb', $this->normalizer->normalizeToSupported('nb-NO', $supported));
        self::assertNull($this->normalizer->normalizeToSupported('xx', $supported));
    }

    /**
     * @brief Legacy /no/ path prefix must resolve against supported locales.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testLegacyPathPrefixResolvesToNb(): void
    {
        $supported = ['fr', 'en', 'de', 'lt', 'nb'];

        self::assertSame('nb', $this->normalizer->normalizePathPrefix('no', $supported));
        self::assertSame('nb', $this->normalizer->normalizePathPrefix('nb', $supported));
    }
}
