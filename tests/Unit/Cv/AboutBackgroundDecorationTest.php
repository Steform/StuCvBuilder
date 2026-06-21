<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\AboutBackgroundDecoration;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for About background decoration enum and legacy migration.
 * @date 2026-05-18
 * @author Stephane H.
 */
final class AboutBackgroundDecorationTest extends TestCase
{
    /**
     * @brief Valid stored values must resolve to matching cases.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testFromStoredAcceptsValidValues(): void
    {
        self::assertSame(AboutBackgroundDecoration::HexZoomMesh, AboutBackgroundDecoration::fromStored('hex_zoom_mesh'));
        self::assertSame(AboutBackgroundDecoration::AmbientParticles, AboutBackgroundDecoration::fromStored('ambient_particles', []));
        self::assertSame(AboutBackgroundDecoration::IsometricGrid, AboutBackgroundDecoration::fromStored('isometric_grid'));
        self::assertSame(AboutBackgroundDecoration::DevCodeRain, AboutBackgroundDecoration::fromStored('dev_code_rain'));
    }

    /**
     * @brief Legacy portfolio styles must migrate to adaptive replacements.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testFromStoredMigratesLegacyPortfolioStyles(): void
    {
        self::assertSame(AboutBackgroundDecoration::HexZoomMesh, AboutBackgroundDecoration::fromStored('depth_fade'));
        self::assertSame(AboutBackgroundDecoration::HexZoomMesh, AboutBackgroundDecoration::fromStored('directional_glow'));
        self::assertSame(AboutBackgroundDecoration::AmbientParticles, AboutBackgroundDecoration::fromStored('subtle_mesh', []));
        self::assertSame(AboutBackgroundDecoration::AmbientParticles, AboutBackgroundDecoration::fromStored('paper_texture', []));
        self::assertSame(AboutBackgroundDecoration::IsometricGrid, AboutBackgroundDecoration::fromStored('accent_guides'));
        self::assertSame(AboutBackgroundDecoration::IsometricGrid, AboutBackgroundDecoration::fromStored('horizontal_sweep'));
    }

    /**
     * @brief Legacy vignette and film_grain keys migrate to replacement styles.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testFromStoredMigratesLegacyVignetteAndFilmGrain(): void
    {
        self::assertSame(
            AboutBackgroundDecoration::HexZoomMesh,
            AboutBackgroundDecoration::fromStored('vignette')
        );
        self::assertSame(
            AboutBackgroundDecoration::AmbientParticles,
            AboutBackgroundDecoration::fromStored('film_grain', [])
        );
    }

    /**
     * @brief Legacy disabled dots flag must map to none when decoration key is absent.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testFromStoredLegacyDotsDisabledMapsToNone(): void
    {
        self::assertSame(
            AboutBackgroundDecoration::None,
            AboutBackgroundDecoration::fromStored(null, ['aboutDotsEnabled' => false])
        );
    }

    /**
     * @brief Legacy dots keys or enabled flag must map to dot grid when decoration key is absent.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testFromStoredLegacyDotsDataMapsToDotGrid(): void
    {
        self::assertSame(
            AboutBackgroundDecoration::DotGrid,
            AboutBackgroundDecoration::fromStored(null, ['aboutDotsColor' => '#88ccff'])
        );
        self::assertSame(
            AboutBackgroundDecoration::DotGrid,
            AboutBackgroundDecoration::fromStored(null, ['aboutDotsEnabled' => true])
        );
    }

    /**
     * @brief Explicit decoration key wins over legacy dots enabled flag.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testFromStoredExplicitKeyOverridesLegacy(): void
    {
        self::assertSame(
            AboutBackgroundDecoration::None,
            AboutBackgroundDecoration::fromStored('none', ['aboutDotsEnabled' => true])
        );
    }

    /**
     * @brief Admin must expose eight selectable decoration modes.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testCasesForAdminReturnsEightModes(): void
    {
        self::assertCount(8, AboutBackgroundDecoration::casesForAdmin());
    }
}
