<?php

declare(strict_types=1);

namespace App\Tests\Functional\Employment;

use PHPUnit\Framework\TestCase;

/**
 * @brief Contract tests for public PDF download with QR stamping.
 */
final class EmploymentPublicPdfQrStampTest extends TestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief CvController must deliver stamped PDFs through delivery service.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testCvControllerUsesStampedPdfDelivery(): void
    {
        $source = @file_get_contents(self::projectRoot().'/src/Controller/CvController.php') ?: '';

        self::assertStringContainsString('EmploymentDocumentPdfDeliveryService', $source);
        self::assertStringContainsString('buildStampedPdfResponse', $source);
        self::assertStringContainsString('deleteFileAfterSend(true)', $source);
    }

    /**
     * @brief Public resolver must expose variant metadata for stamping.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testResolverReturnsVariantMetadata(): void
    {
        $source = @file_get_contents(self::projectRoot().'/src/Service/Employment/EmploymentPublicDocumentPdfResolver.php') ?: '';

        self::assertStringContainsString("'variant' => \$variant", $source);
    }
}
