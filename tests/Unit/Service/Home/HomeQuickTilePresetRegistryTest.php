<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Home;

use App\Service\Home\HomeQuickTilePresetRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see HomeQuickTilePresetRegistry}.
 * @date 2026-05-17
 * @author Stephane H.
 */
final class HomeQuickTilePresetRegistryTest extends TestCase
{
    private HomeQuickTilePresetRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new HomeQuickTilePresetRegistry();
    }

    /**
     * @brief Each preset style_1 through style_14 must return non-empty CSS declarations.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testAllPresetStylesReturnNonEmptyCss(): void
    {
        foreach (HomeQuickTilePresetRegistry::PRESET_STYLES as $style) {
            $css = $this->registry->getPresetCss($style);
            self::assertNotSame('', trim($css), 'Preset '.$style.' must not be empty');
        }
    }

    /**
     * @brief Custom mode is valid for persistence but not for preset CSS lookup.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testCustomStyleIsValidButNotResolvableViaGetPresetCss(): void
    {
        self::assertTrue($this->registry->isValidStyle(HomeQuickTilePresetRegistry::STYLE_CUSTOM));

        $this->expectException(InvalidArgumentException::class);
        $this->registry->getPresetCss(HomeQuickTilePresetRegistry::STYLE_CUSTOM);
    }

    /**
     * @brief Unknown style keys must be rejected by validation.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testInvalidStyleIsRejected(): void
    {
        self::assertFalse($this->registry->isValidStyle('style_99'));
    }
}
