<?php

declare(strict_types=1);

namespace App\Service\Employment;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Per-locale upload payload for employment document variant forms.
 */
final class EmploymentDocumentLocaleAssetInput
{
    /**
     * @brief Build locale asset input row.
     *
     * @param string $locale Locale code.
     * @param UploadedFile|null $templateFile Optional template upload.
     * @param UploadedFile|null $pdfFile Optional PDF upload.
     * @param bool $removeTemplate Clear existing template when true.
     * @param bool $removePdf Clear existing PDF when true.
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function __construct(
        public readonly string $locale,
        public readonly ?UploadedFile $templateFile = null,
        public readonly ?UploadedFile $pdfFile = null,
        public readonly bool $removeTemplate = false,
        public readonly bool $removePdf = false,
    ) {
    }
}
