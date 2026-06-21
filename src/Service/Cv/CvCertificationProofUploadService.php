<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @brief Store and validate CV certification proof PDF uploads.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class CvCertificationProofUploadService
{
    private const MAX_BYTES = 5242880;

    /**
     * @param string $projectDir Symfony project root directory.
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Store an uploaded certification proof PDF and return the relative public path.
     *
     * @param UploadedFile $uploadedFile Validated upload.
     * @param string $entryId Certification entry UUID for filename suffix.
     * @return string Relative path under public/.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function store(UploadedFile $uploadedFile, string $entryId): string
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.certification_invalid_pdf');
        }

        if ($uploadedFile->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.certification_invalid_pdf');
        }

        $mimeType = (string) $uploadedFile->getMimeType();
        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));
        if ($mimeType !== 'application/pdf' || $extension !== 'pdf') {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.certification_invalid_pdf');
        }

        $binary = (string) file_get_contents((string) $uploadedFile->getPathname());
        if (!str_starts_with($binary, '%PDF-')) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flash.certification_invalid_pdf');
        }

        $targetRelativeDirectory = CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX;
        $targetDirectory = rtrim($this->projectDir, '/').'/public/'.$targetRelativeDirectory;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Unable to create certification proof upload directory.');
        }

        $safeId = preg_replace('/[^a-f0-9-]/i', '', $entryId) ?? '';
        $targetFilename = sprintf('certification-proof-%s-%s.pdf', substr($safeId, 0, 8), bin2hex(random_bytes(4)));
        $uploadedFile->move($targetDirectory, $targetFilename);

        return $targetRelativeDirectory.$targetFilename;
    }

    /**
     * @brief Delete a previously stored custom certification proof PDF when replaced or removed.
     *
     * @param string $relativePath Relative path under public/.
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function deleteIfStored(string $relativePath): void
    {
        if (!str_starts_with($relativePath, CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX)) {
            return;
        }

        $absolutePath = rtrim($this->projectDir, '/').'/public/'.$relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
