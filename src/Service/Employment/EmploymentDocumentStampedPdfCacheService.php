<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentDocumentVariant;
use App\Exception\Employment\EmploymentDocumentPdfStampException;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Resolves persisted stamped PDF files, generating them on demand when missing or stale.
 */
final class EmploymentDocumentStampedPdfCacheService
{
    /**
     * @brief Build stamped PDF cache service.
     *
     * @param EmploymentDocumentPdfQrStampService $stampService QR stamp service.
     * @param EmploymentDocumentStorageService $storageService Employment file storage.
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function __construct(
        private readonly EmploymentDocumentPdfQrStampService $stampService,
        private readonly EmploymentDocumentStorageService $storageService,
    ) {
    }

    /**
     * @brief Return cached stamped PDF path or generate, persist, and return it.
     *
     * @param string $kind cv or lm.
     * @param string $locale Locale code used for storage path.
     * @param string $absoluteSourcePath Readable absolute path to the source PDF.
     * @param EmploymentDocumentVariant $variant Variant providing placement coordinates.
     * @param string $formatCode Company format code encoded in the QR.
     * @return array{absolutePath: string, isTemporary: bool}
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function resolve(
        string $kind,
        string $locale,
        string $absoluteSourcePath,
        EmploymentDocumentVariant $variant,
        string $formatCode,
    ): array {
        $variantId = $variant->getId();
        if ($variantId === null || $variantId < 1) {
            throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.failed');
        }

        $cachedAbsolute = $this->storageService->resolveStampedAbsolutePath(
            $kind,
            $variantId,
            $locale,
            $formatCode,
        );
        if (
            $cachedAbsolute !== null
            && $this->isCacheUsable($cachedAbsolute, $absoluteSourcePath, $variant)
        ) {
            return [
                'absolutePath' => $cachedAbsolute,
                'isTemporary' => false,
            ];
        }

        $this->storageService->deleteStampedPdfsForLocale($kind, $variantId, $locale);

        try {
            $tempStampedPath = $this->stampService->stamp($absoluteSourcePath, $variant, $formatCode);

            if (!$this->isPdfReadableByFpdi($tempStampedPath)) {
                throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.source_unreadable');
            }

            if (!$this->isStampedOutputSubstantial($tempStampedPath, $absoluteSourcePath)) {
                throw new EmploymentDocumentPdfStampException('employment.documents.pdf_stamp.failed');
            }

            $persistedAbsolute = $this->storageService->persistStampedPdf(
                $kind,
                $variantId,
                $locale,
                $formatCode,
                $tempStampedPath,
            );
        } finally {
            if (isset($tempStampedPath) && is_file($tempStampedPath)) {
                @unlink($tempStampedPath);
            }
        }

        return [
            'absolutePath' => $persistedAbsolute,
            'isTemporary' => false,
        ];
    }

    /**
     * @brief Check whether a cached stamped PDF can be delivered as-is.
     *
     * @param string $cachedAbsolute Absolute stamped PDF path.
     * @param string $sourceAbsolute Absolute source PDF path.
     * @param EmploymentDocumentVariant $variant Variant providing placement coordinates.
     * @return bool
     * @date 2026-06-15
     * @author Stephane H.
     */
    private function isCacheUsable(
        string $cachedAbsolute,
        string $sourceAbsolute,
        EmploymentDocumentVariant $variant,
    ): bool {
        if (!is_readable($cachedAbsolute)) {
            return false;
        }

        $cachedSize = filesize($cachedAbsolute);
        if ($cachedSize === false || $cachedSize < 8) {
            return false;
        }

        $header = @file_get_contents($cachedAbsolute, false, null, 0, 5);
        if ($header !== '%PDF-') {
            return false;
        }

        if (!$this->isCacheFresh($cachedAbsolute, $sourceAbsolute)) {
            return false;
        }

        $cachedMtime = @filemtime($cachedAbsolute);
        if ($cachedMtime === false || $cachedMtime < $variant->getUpdatedAt()->getTimestamp()) {
            return false;
        }

        if (!$this->isPdfReadableByFpdi($cachedAbsolute)) {
            return false;
        }

        return $this->isStampedOutputSubstantial($cachedAbsolute, $sourceAbsolute);
    }

    /**
     * @brief Check whether a stamped PDF file is large enough to contain real CV content.
     *
     * @param string $stampedAbsolute Absolute stamped PDF path.
     * @param string $sourceAbsolute Absolute source PDF path.
     * @return bool
     * @date 2026-06-15
     * @author Stephane H.
     */
    private function isStampedOutputSubstantial(string $stampedAbsolute, string $sourceAbsolute): bool
    {
        $stampedSize = filesize($stampedAbsolute);
        $sourceSize = filesize($sourceAbsolute);
        if ($stampedSize === false || $sourceSize === false || $sourceSize < 1) {
            return false;
        }

        $minimumSize = (int) max(40960, $sourceSize * 0.05);

        return $stampedSize >= $minimumSize;
    }

    /**
     * @brief Check whether a PDF file can be parsed by FPDI.
     *
     * @param string $absolutePath Absolute PDF path.
     * @return bool
     * @date 2026-06-15
     * @author Stephane H.
     */
    private function isPdfReadableByFpdi(string $absolutePath): bool
    {
        if (!class_exists(Fpdi::class)) {
            return true;
        }

        try {
            $pdf = new Fpdi('P', 'mm');
            $pdf->setSourceFile($absolutePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @brief Check whether cached stamped PDF is at least as new as the source PDF.
     *
     * @param string $cachedAbsolute Absolute stamped PDF path.
     * @param string $sourceAbsolute Absolute source PDF path.
     * @return bool
     * @date 2026-06-15
     * @author Stephane H.
     */
    private function isCacheFresh(string $cachedAbsolute, string $sourceAbsolute): bool
    {
        $cachedMtime = @filemtime($cachedAbsolute);
        $sourceMtime = @filemtime($sourceAbsolute);

        if ($cachedMtime === false || $sourceMtime === false) {
            return false;
        }

        return $cachedMtime >= $sourceMtime;
    }
}
