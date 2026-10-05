<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\EmploymentDocumentVariant;
use App\Employment\EmploymentDocumentKind;
use App\Service\Employment\EmploymentCvRecruiterUrlBuilder;
use App\Service\Employment\EmploymentDocumentPdfQrStampService;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for employment PDF QR stamping.
 */
final class EmploymentDocumentPdfQrStampServiceTest extends TestCase
{
    /**
     * @brief Convert centimeter placement values to millimeters.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testCentimetersToMillimeters(): void
    {
        self::assertSame(25.0, EmploymentDocumentPdfQrStampService::centimetersToMillimeters('2.50'));
        self::assertSame(70.0, EmploymentDocumentPdfQrStampService::centimetersToMillimeters('7.00'));
    }

    /**
     * @brief Stamp produces a different readable PDF when libraries are available.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testStampCreatesReadablePdfOutput(): void
    {
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            self::markTestSkipped('FPDI/TCPDF library is not installed.');
        }

        $fixturePath = dirname(__DIR__, 3).'/Fixtures/Employment/minimal-stamp-source.pdf';
        self::assertFileExists($fixturePath);

        $urlBuilder = $this->createMock(EmploymentCvRecruiterUrlBuilder::class);
        $urlBuilder->method('build')->willReturnCallback(
            static fn (string $formatCode, string $kind): string => $kind === EmploymentDocumentKind::LM
                ? 'https://example.test/cv/lm-pdf?format='.$formatCode
                : 'https://example.test/cv/?format='.$formatCode,
        );

        $service = new EmploymentDocumentPdfQrStampService($urlBuilder);
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::CV, 'Stamp test');
        $variant->setPlacement('2.50', '2.50', '2.00');

        $outputPath = $service->stamp($fixturePath, $variant, 'Ab3xY9kLm2Qp');

        try {
            self::assertFileExists($outputPath);
            self::assertGreaterThan(filesize($fixturePath), filesize($outputPath));
            self::assertStringStartsWith('%PDF', (string) file_get_contents($outputPath, false, null, 0, 4));
        } finally {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
    }

    /**
     * @brief Stamp succeeds on PDFs that free FPDI cannot parse by rewriting via Ghostscript.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testStampRewritesFpdiIncompatiblePdfViaGhostscript(): void
    {
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            self::markTestSkipped('FPDI/TCPDF library is not installed.');
        }

        if (!is_executable('/usr/bin/gs') && !is_executable('/usr/local/bin/gs')) {
            self::markTestSkipped('Ghostscript is not installed.');
        }

        $fixturePath = dirname(__DIR__, 3).'/Fixtures/Employment/fpdi-incompatible-object-streams.pdf';
        self::assertFileExists($fixturePath);

        try {
            $probe = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm');
            $probe->setSourceFile($fixturePath);
            self::markTestSkipped('Fixture is already FPDI-compatible; cannot assert rewrite path.');
        } catch (\Throwable) {
            // Expected: free FPDI parser rejects this fixture.
        }

        $urlBuilder = $this->createMock(EmploymentCvRecruiterUrlBuilder::class);
        $urlBuilder->method('build')->willReturn('https://example.test/cv/lm-pdf?format=Ab3xY9kLm2Qp');

        $service = new EmploymentDocumentPdfQrStampService($urlBuilder);
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::LM, 'Stamp rewrite test');
        $variant->setPlacement('2.54', '7.06', '2.00');

        $outputPath = $service->stamp($fixturePath, $variant, 'Ab3xY9kLm2Qp');

        try {
            self::assertFileExists($outputPath);
            self::assertGreaterThan(40960, filesize($outputPath));
            self::assertStringStartsWith('%PDF', (string) file_get_contents($outputPath, false, null, 0, 4));

            $readable = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm');
            self::assertGreaterThanOrEqual(1, $readable->setSourceFile($outputPath));
        } finally {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
        }
    }
}
