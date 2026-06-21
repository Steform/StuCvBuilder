<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\EmploymentDocumentVariant;

/**
 * Produces stamped employment PDF files ready for HTTP delivery.
 */
final class EmploymentDocumentPdfDeliveryService
{
    /**
     * @brief Build stamped PDF delivery service.
     *
     * @param EmploymentDocumentStampedPdfCacheService $stampedPdfCacheService Stamped PDF cache service.
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function __construct(
        private readonly EmploymentDocumentStampedPdfCacheService $stampedPdfCacheService,
    ) {
    }

    /**
     * @brief Resolve stamped PDF (cached or generated on demand) and return delivery metadata.
     *
     * @param string $kind cv or lm.
     * @param string $locale Locale code for cache path.
     * @param string $absoluteSourcePath Readable absolute path to the stored PDF.
     * @param EmploymentDocumentVariant $variant Variant providing placement coordinates.
     * @param string $formatCode Company format code encoded in the QR.
     * @param string $downloadFilename Suggested download filename.
     * @return array{absolutePath: string, downloadFilename: string, isTemporary: bool}
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function deliver(
        string $kind,
        string $locale,
        string $absoluteSourcePath,
        EmploymentDocumentVariant $variant,
        string $formatCode,
        string $downloadFilename,
    ): array {
        $cached = $this->stampedPdfCacheService->resolve(
            $kind,
            $locale,
            $absoluteSourcePath,
            $variant,
            $formatCode,
        );

        return [
            'absolutePath' => $cached['absolutePath'],
            'downloadFilename' => $downloadFilename,
            'isTemporary' => $cached['isTemporary'],
        ];
    }
}
