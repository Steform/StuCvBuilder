<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\AboutSectionAtmospherePresetRegistry;
use App\Service\Security\CssSanitizerService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see AboutSectionAtmospherePresetRegistry}.
 * @date 2026-05-16
 * @author Stephane H.
 */
final class AboutSectionAtmospherePresetRegistryTest extends TestCase
{
    private AboutSectionAtmospherePresetRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AboutSectionAtmospherePresetRegistry(new CssSanitizerService());
    }

    /**
     * @brief Each preset style_1 through style_15 must return valid definition.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testAllPresetStylesReturnDefinitions(): void
    {
        foreach (AboutSectionAtmospherePresetRegistry::PRESET_STYLES as $style) {
            $def = $this->registry->getPresetDefinition($style);
            self::assertNotSame('', $def['primary']);
            self::assertNotSame('', $def['secondary']);
            self::assertGreaterThanOrEqual(0.0, $def['haloStrength']);
            self::assertLessThanOrEqual(1.0, $def['haloStrength']);
        }
    }

    /**
     * @brief Style 1 must match legacy default atmosphere values.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testStyle1MatchesLegacyDefaults(): void
    {
        $def = $this->registry->getPresetDefinition('style_1');

        self::assertSame('#010a22', $def['primary']);
        self::assertSame('#03215a', $def['secondary']);
        self::assertSame(0.65, $def['haloStrength']);
        self::assertSame('', $def['sectionBackgroundOverride']);
    }

    /**
     * @brief Override blocks must retain semicolons after sanitization.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testStyleWithOverrideKeepsSemicolons(): void
    {
        $def = $this->registry->getPresetDefinition('style_2');
        self::assertNotSame('', $def['sectionBackgroundOverride']);
        self::assertStringContainsString(';', $def['sectionBackgroundOverride']);
    }

    /**
     * @brief Custom mode is valid but not resolvable via getPresetDefinition.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testCustomStyleIsValidButNotResolvable(): void
    {
        self::assertTrue($this->registry->isValidStyle(AboutSectionAtmospherePresetRegistry::STYLE_CUSTOM));

        $this->expectException(InvalidArgumentException::class);
        $this->registry->getPresetDefinition(AboutSectionAtmospherePresetRegistry::STYLE_CUSTOM);
    }
}
