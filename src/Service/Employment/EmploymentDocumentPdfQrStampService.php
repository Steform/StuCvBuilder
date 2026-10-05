<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentDocumentVariant;
use App\Exception\Employment\EmploymentDocumentPdfStampException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Stamps a QR code onto the first page of an employment PDF using variant placement in centimeters.
 */
final class EmploymentDocumentPdfQrStampService
{
    /**
     * @brief Build PDF QR stamp service.
     *
     * @param EmploymentCvRecruiterUrlBuilder $recruiterUrlBuilder Recruiter URL builder.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function __construct(
        private readonly EmploymentCvRecruiterUrlBuilder $recruiterUrlBuilder,
    ) {
    }

    /**
     * @brief Stamp QR code onto a copy of the source PDF and return the output path.
     *
     * @param string $sourceAbsolutePath Readable absolute path to the stored PDF.
     * @param EmploymentDocumentVariant $variant Variant providing placement coordinates.
     * @param string $formatCode Company format code for the encoded recruiter URL.
     * @return string Absolute path to a temporary stamped PDF file.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function stamp(string $sourceAbsolutePath, EmploymentDocumentVariant $variant, string $formatCode): string
    {
        if (!class_exists(Fpdi::class)) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.libraries_missing');
        }

        if (!is_readable($sourceAbsolutePath)) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.source_unreadable');
        }

        $recruiterUrl = $this->recruiterUrlBuilder->build($formatCode, $variant->getKind());

        try {
            return $this->overlayQrOnPdf($sourceAbsolutePath, $variant, $recruiterUrl);
        } catch (EmploymentDocumentPdfStampException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.failed', $exception);
        }
    }

    /**
     * @brief Convert centimeter decimal string to millimeters for TCPDF coordinates.
     *
     * @param string $centimeters Placement value stored in centimeters.
     * @return float Millimeter value.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public static function centimetersToMillimeters(string $centimeters): float
    {
        return (float) $centimeters * 10.0;
    }

    /**
     * @brief Import source PDF pages and overlay QR on the first page.
     *
     * @param string $sourceAbsolutePath Source PDF absolute path.
     * @param EmploymentDocumentVariant $variant Variant placement values.
     * @param string $recruiterUrl Absolute recruiter URL encoded in the QR.
     * @return string Absolute path to stamped PDF.
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function overlayQrOnPdf(
        string $sourceAbsolutePath,
        EmploymentDocumentVariant $variant,
        string $recruiterUrl,
    ): string {
        $compatibleSourcePath = $sourceAbsolutePath;
        $temporaryRewritePath = null;

        try {
            if (!$this->canFpdiOpen($sourceAbsolutePath)) {
                $temporaryRewritePath = $this->rewritePdfForFpdi($sourceAbsolutePath);
                if ($temporaryRewritePath === null || !$this->canFpdiOpen($temporaryRewritePath)) {
                    throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.source_unreadable');
                }
                $compatibleSourcePath = $temporaryRewritePath;
            }

            return $this->buildStampedPdfFromCompatibleSource($compatibleSourcePath, $variant, $recruiterUrl);
        } finally {
            if ($temporaryRewritePath !== null && is_file($temporaryRewritePath)) {
                @unlink($temporaryRewritePath);
            }
        }
    }

    /**
     * @brief Stamp QR onto an FPDI-readable PDF source.
     *
     * @param string $sourceAbsolutePath Compatible source PDF absolute path.
     * @param EmploymentDocumentVariant $variant Variant placement values.
     * @param string $recruiterUrl Absolute recruiter URL encoded in the QR.
     * @return string Absolute path to stamped PDF.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function buildStampedPdfFromCompatibleSource(
        string $sourceAbsolutePath,
        EmploymentDocumentVariant $variant,
        string $recruiterUrl,
    ): string {
        $pdf = new Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        try {
            $pageCount = $pdf->setSourceFile($sourceAbsolutePath);
        } catch (\Throwable $exception) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.source_unreadable', $exception);
        }

        if ($pageCount < 1) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.source_unreadable');
        }

        $xMm = self::centimetersToMillimeters($variant->getLinkX());
        $yMm = self::centimetersToMillimeters($variant->getLinkY());
        $sizeMm = self::centimetersToMillimeters($variant->getSquareSizeCm());

        $qrStyle = [
            'border' => false,
            'padding' => 0,
            'hpadding' => 0,
            'vpadding' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
        ];

        for ($pageNumber = 1; $pageNumber <= $pageCount; ++$pageNumber) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            if ($pageNumber === 1) {
                try {
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->Rect($xMm, $yMm, $sizeMm, $sizeMm, 'F');
                    $pdf->write2DBarcode(
                        $recruiterUrl,
                        'QRCODE,L',
                        $xMm,
                        $yMm,
                        $sizeMm,
                        $sizeMm,
                        $qrStyle,
                        'N',
                    );
                } catch (\Throwable $exception) {
                    throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.qr_failed', $exception);
                }
            }
        }

        $outputPath = $this->buildTempPdfPath();
        $pdf->SetCompression(false);
        $pdf->setPDFVersion('1.5');
        $pdf->Output($outputPath, 'F');

        if (!is_readable($outputPath)) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.failed');
        }

        return $outputPath;
    }

    /**
     * @brief Check whether the free FPDI parser can open a PDF file.
     *
     * @param string $absolutePath Absolute PDF path.
     * @return bool True when FPDI can parse the file.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function canFpdiOpen(string $absolutePath): bool
    {
        try {
            $pdf = new Fpdi('P', 'mm');
            $pdf->setSourceFile($absolutePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @brief Rewrite a PDF with Ghostscript into an FPDI-compatible PDF 1.4 file.
     *
     * Some Word/export PDFs use object streams or compression that free FPDI cannot parse.
     * Ghostscript flattens them into a classic PDF that FPDI can import.
     *
     * @param string $sourceAbsolutePath Source PDF absolute path.
     * @return string|null Absolute rewritten PDF path, or null when rewrite is unavailable/failed.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function rewritePdfForFpdi(string $sourceAbsolutePath): ?string
    {
        $gsBinary = $this->resolveGhostscriptBinary();
        if ($gsBinary === null) {
            return null;
        }

        $outputPath = $this->buildTempPdfPath();
        $command = [
            $gsBinary,
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            '-dSAFER',
            '-sOutputFile='.$outputPath,
            $sourceAbsolutePath,
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptorSpec, $pipes, null, null);
        if (!is_resource($process)) {
            @unlink($outputPath);

            return null;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !is_readable($outputPath)) {
            @unlink($outputPath);

            return null;
        }

        $size = filesize($outputPath);
        $header = @file_get_contents($outputPath, false, null, 0, 5);
        if ($size === false || $size < 8 || $header !== '%PDF-') {
            @unlink($outputPath);

            return null;
        }

        return $outputPath;
    }

    /**
     * @brief Resolve Ghostscript executable path when available on the host.
     *
     * @return string|null Absolute or PATH-resolvable gs binary, or null.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function resolveGhostscriptBinary(): ?string
    {
        foreach (['/usr/bin/gs', '/usr/local/bin/gs'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @brief Allocate a temporary output PDF path.
     *
     * @return string Absolute PDF path.
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function buildTempPdfPath(): string
    {
        $tempBase = tempnam(sys_get_temp_dir(), 'cv_qr_pdf_');
        if ($tempBase === false) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.temp_failed');
        }

        $pdfPath = $tempBase.'.pdf';
        @unlink($tempBase);

        return $pdfPath;
    }
}
