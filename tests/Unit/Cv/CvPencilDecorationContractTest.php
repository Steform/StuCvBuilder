<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CvPencilDecorationContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see CvPencilDecorationContract}.
 *
 * @date 2026-06-08
 * @author Stephane H.
 */
final class CvPencilDecorationContractTest extends TestCase
{
    public function testNormalizeUsesDefaultsWhenRawIsInvalid(): void
    {
        $normalized = CvPencilDecorationContract::normalize(null);

        self::assertTrue($normalized['enabled']);
        self::assertSame(93, $normalized['lightToneMixPercent']);
        self::assertSame(90, $normalized['darkToneMixPercent']);
    }

    public function testIsEnabledFromPayloadDefaultsToTrueWhenUnset(): void
    {
        self::assertTrue(CvPencilDecorationContract::isEnabledFromPayload([]));
    }

    public function testNormalizeToneMixPercentClampsValues(): void
    {
        self::assertSame(0, CvPencilDecorationContract::normalizeToneMixPercent(-12, 50));
        self::assertSame(100, CvPencilDecorationContract::normalizeToneMixPercent(180, 50));
        self::assertSame(42, CvPencilDecorationContract::normalizeToneMixPercent('41.6', 50));
    }

    public function testMergeSubmittedFromCvDataRequestPersistsToggleAndSliders(): void
    {
        $request = Request::create('/', 'POST', [
            'cv_pencil_decoration_enabled' => ['0', '1'],
            'cv_pencil_light_tone_mix_percent' => '77',
            'cv_pencil_dark_tone_mix_percent' => '66',
        ]);

        $payload = CvPencilDecorationContract::mergeSubmittedFromCvDataRequest([], $request);

        self::assertSame([
            'enabled' => true,
            'lightToneMixPercent' => 77,
            'darkToneMixPercent' => 66,
        ], $payload[CvPencilDecorationContract::KEY]);
    }

    public function testMergeSubmittedFromCvDataRequestCanDisablePencil(): void
    {
        $request = Request::create('/', 'POST', [
            'cv_pencil_decoration_enabled' => '0',
        ]);

        $payload = CvPencilDecorationContract::mergeSubmittedFromCvDataRequest([], $request);

        self::assertFalse($payload[CvPencilDecorationContract::KEY]['enabled']);
    }
}
