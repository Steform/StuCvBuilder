<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\SituationBackgroundTexture;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for Situation background texture enum.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
final class SituationBackgroundTextureTest extends TestCase
{
    /**
     * @brief Valid stored values must resolve to matching cases.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testFromStoredAcceptsValidValues(): void
    {
        self::assertSame(SituationBackgroundTexture::Texture1, SituationBackgroundTexture::fromStored('texture_1'));
        self::assertSame(SituationBackgroundTexture::Texture6, SituationBackgroundTexture::fromStored('texture_6'));
    }

    /**
     * @brief Invalid or missing values must fall back to texture_1.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testFromStoredFallsBackToDefault(): void
    {
        self::assertSame(SituationBackgroundTexture::Texture1, SituationBackgroundTexture::fromStored(null));
        self::assertSame(SituationBackgroundTexture::Texture1, SituationBackgroundTexture::fromStored('invalid'));
    }

    /**
     * @brief Each case must map to the expected WebP asset path.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testRelativeAssetPaths(): void
    {
        self::assertSame('images/cv/textures/texture1.webp', SituationBackgroundTexture::Texture1->relativeAssetPath());
        self::assertSame('images/cv/textures/texture6.webp', SituationBackgroundTexture::Texture6->relativeAssetPath());
    }

    /**
     * @brief Admin cases must expose all six textures.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testCasesForAdminIncludesAllTextures(): void
    {
        self::assertCount(6, SituationBackgroundTexture::casesForAdmin());
    }

    /**
     * @brief Mask tile size must match the on-disk WebP dimensions when the asset exists.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testMaskTileSizePxMatchesTextureAsset(): void
    {
        $tile = SituationBackgroundTexture::Texture1->maskTileSizePx();
        $assetPath = dirname(__DIR__, 3).'/public/images/cv/textures/texture1.webp';

        self::assertGreaterThan(0, $tile['widthPx']);
        self::assertGreaterThan(0, $tile['heightPx']);

        if (is_file($assetPath)) {
            $size = getimagesize($assetPath);
            self::assertIsArray($size);
            self::assertSame((int) $size[0], $tile['widthPx']);
            self::assertSame((int) $size[1], $tile['heightPx']);
        }
    }
}
