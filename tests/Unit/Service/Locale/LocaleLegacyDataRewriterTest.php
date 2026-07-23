<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Locale;

use App\Service\Locale\LocaleLegacyDataRewriter;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for legacy locale data rewriting.
 *
 * @date 2026-07-05
 * @author Stephane H.
 */
final class LocaleLegacyDataRewriterTest extends TestCase
{
    private LocaleLegacyDataRewriter $rewriter;

    protected function setUp(): void
    {
        $this->rewriter = new LocaleLegacyDataRewriter();
    }

    /**
     * @brief Nested locale maps must migrate legacy no keys and values to nb.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testRewriteStructureMigratesNestedLocaleMaps(): void
    {
        $input = [
            'experienceEntriesByLocale' => [
                'no' => [['id' => '1']],
                'fr' => [['id' => '2']],
            ],
            'defaultLocale' => 'no',
        ];

        $output = $this->rewriter->rewriteStructure($input);

        self::assertArrayHasKey('nb', $output['experienceEntriesByLocale']);
        self::assertArrayNotHasKey('no', $output['experienceEntriesByLocale']);
        self::assertSame('nb', $output['defaultLocale']);
    }

    /**
     * @brief Locale configuration payload must rewrite active locales and default locale.
     *
     * @return void
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function testRewriteLocaleConfiguration(): void
    {
        $output = $this->rewriter->rewriteLocaleConfiguration([
            'active_locales' => ['fr', 'no', 'en'],
            'default_locale' => 'no',
        ]);

        self::assertSame(['fr', 'nb', 'en'], $output['active_locales']);
        self::assertSame('nb', $output['default_locale']);
    }
}
