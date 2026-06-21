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
