<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Employment\EmploymentDocumentKind;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Store employment CV/LM template and PDF files under var/employment_documents/.
 */
class EmploymentDocumentStorageService
{
    private const MAX_BYTES = 52428800;

    private const TEMPLATE_EXTENSIONS = [
        'doc', 'docx', 'dot', 'dotx', 'odt', 'rtf',
        'psd', 'ai', 'indd', 'idml',
        'pages', 'key', 'ppt', 'pptx',
    ];

    /**
     * @brief Build document storage service.
     *
     * @param string $projectDir Symfony project root.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Store template source file and return relative storage path.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @param string $locale Locale code.
     * @param UploadedFile $upload Valid upload.
     * @return string Relative path from project root.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function storeTemplate(string $kind, int $variantId, string $locale, UploadedFile $upload): string
    {
        $this->assertUploadValid($upload, self::TEMPLATE_EXTENSIONS, false);

        return $this->store($kind, $variantId, $locale, 'template', $upload);
    }

    /**
     * @brief Store PDF file and return relative storage path.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @param string $locale Locale code.
     * @param UploadedFile $upload Valid upload.
     * @return string Relative path from project root.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function storePdf(string $kind, int $variantId, string $locale, UploadedFile $upload): string
    {
        $this->assertUploadValid($upload, ['pdf'], true);

        return $this->store($kind, $variantId, $locale, 'pdf', $upload);
    }

    /**
     * @brief Delete stored file when path belongs to employment documents tree.
     *
     * @param string|null $relativePath Relative path from project root.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function deleteIfNeeded(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $absolute = $this->projectDir.'/'.$relativePath;
        $base = realpath($this->projectDir.'/var/employment_documents');
        $target = realpath($absolute);
        if ($base === false || $target === false || !str_starts_with($target, $base)) {
            return;
        }

        if (is_file($target)) {
            @unlink($target);
        }
    }

    /**
     * @brief Resolve absolute filesystem path for a stored relative path.
     *
     * @param string $relativePath Relative path from project root.
     * @return string|null Absolute path when file exists inside employment tree.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolveAbsolutePath(string $relativePath): ?string
    {
        $absolute = $this->projectDir.'/'.$relativePath;
        $base = realpath($this->projectDir.'/var/employment_documents');
        $target = realpath($absolute);
        if ($base === false || $target === false || !str_starts_with($target, $base) || !is_file($target)) {
            return null;
        }

        return $target;
    }

    /**
     * @brief Resolve absolute path for a persisted stamped PDF when it exists.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @param string $locale Locale code.
     * @param string $formatCode Company format code encoded in the QR.
     * @return string|null Absolute path when stamped file exists.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function resolveStampedAbsolutePath(
        string $kind,
        int $variantId,
        string $locale,
        string $formatCode,
    ): ?string {
        return $this->resolveAbsolutePath($this->buildStampedRelativePath($kind, $variantId, $locale, $formatCode));
    }

    /**
     * @brief Persist a freshly stamped PDF under the employment documents tree.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @param string $locale Locale code.
     * @param string $formatCode Company format code encoded in the QR.
     * @param string $absoluteTempPath Readable absolute path to a temporary stamped PDF.
     * @return string Absolute path to the persisted stamped PDF.
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function persistStampedPdf(
        string $kind,
        int $variantId,
        string $locale,
        string $formatCode,
        string $absoluteTempPath,
    ): string {
        if (!is_readable($absoluteTempPath)) {
            throw new \RuntimeException('employment.documents.pdf_stamp.failed');
        }

        $relativePath = $this->buildStampedRelativePath($kind, $variantId, $locale, $formatCode);
        $absolutePath = $this->projectDir.'/'.$relativePath;
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('employment.documents.flash.storage_failed');
        }

        if (is_file($absolutePath) && !@unlink($absolutePath)) {
            throw new \RuntimeException('employment.documents.flash.storage_failed');
        }

        if (!@copy($absoluteTempPath, $absolutePath)) {
            throw new \RuntimeException('employment.documents.flash.storage_failed');
        }

        return $absolutePath;
    }

    /**
     * @brief Delete cached stamped PDF files for one locale directory.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @param string $locale Locale code.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function deleteStampedPdfsForLocale(string $kind, int $variantId, string $locale): void
    {
        $directory = $this->buildLocaleDirectoryAbsolutePath($kind, $variantId, $locale);
        if ($directory === null) {
            return;
        }

        $this->deleteStampedFilesInDirectory($directory);
    }

    /**
     * @brief Delete cached stamped PDF files for all locales of a variant.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function deleteStampedPdfsForVariant(string $kind, int $variantId): void
    {
        $variantDirectory = sprintf(
            '%s/var/employment_documents/%s/%d',
            $this->projectDir,
            $kind,
            $variantId,
        );

        if (!is_dir($variantDirectory)) {
            return;
        }

        foreach (scandir($variantDirectory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $localeDirectory = $variantDirectory.'/'.$entry;
            if (is_dir($localeDirectory)) {
                $this->deleteStampedFilesInDirectory($localeDirectory);
            }
        }
    }

    /**
     * @brief Delete all cached stamped PDF files under the employment documents tree.
     *
     * @return void
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function purgeAllStampedPdfCaches(): void
    {
        $root = $this->projectDir.'/var/employment_documents';
        if (!is_dir($root)) {
            return;
        }

        foreach (scandir($root) ?: [] as $kindEntry) {
            if ($kindEntry === '.' || $kindEntry === '..') {
                continue;
            }

            $kindDirectory = $root.'/'.$kindEntry;
            if (!is_dir($kindDirectory)) {
                continue;
            }

            foreach (scandir($kindDirectory) ?: [] as $variantEntry) {
                if ($variantEntry === '.' || $variantEntry === '..') {
                    continue;
                }

                $localeParent = $kindDirectory.'/'.$variantEntry;
                if (!is_dir($localeParent)) {
                    continue;
                }

                foreach (scandir($localeParent) ?: [] as $localeEntry) {
                    if ($localeEntry === '.' || $localeEntry === '..') {
                        continue;
                    }

                    $localeDirectory = $localeParent.'/'.$localeEntry;
                    if (is_dir($localeDirectory)) {
                        $this->deleteStampedFilesInDirectory($localeDirectory);
                    }
                }
            }
        }
    }

    /**
     * @brief Build relative storage path for a stamped PDF file.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @param string $locale Locale code.
     * @param string $formatCode Company format code encoded in the QR.
     * @return string Relative path from project root.
     * @date 2026-06-15
     * @author Stephane H.
     */
    public function buildStampedRelativePath(
        string $kind,
        int $variantId,
        string $locale,
        string $formatCode,
    ): string {
        $safeLocale = preg_replace('/[^a-z0-9_-]/i', '', $locale) ?? 'locale';
        $formatKey = $this->normalizeStampedFormatKey($formatCode);

        $revision = $kind === EmploymentDocumentKind::LM ? 'lm-tight18' : 'tight18';

        return sprintf(
            'var/employment_documents/%s/%d/%s/stamped-%s-%s.pdf',
            $kind,
            $variantId,
            $safeLocale,
            $formatKey,
            $revision,
        );
    }

    /**
     * @brief Normalize format code for stamped PDF filename segment.
     *
     * @param string $formatCode Company format code or empty string.
     * @return string Safe filename segment.
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function normalizeStampedFormatKey(string $formatCode): string
    {
        $formatCode = trim($formatCode);
        if ($formatCode === '') {
            return 'default';
        }

        $normalized = preg_replace('/[^a-zA-Z0-9_-]/', '', $formatCode) ?? '';

        return $normalized !== '' ? strtolower($normalized) : 'default';
    }

    /**
     * @brief Resolve locale directory absolute path inside employment storage tree.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant primary key.
     * @param string $locale Locale code.
     * @return string|null Absolute directory path or null when outside storage tree.
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function buildLocaleDirectoryAbsolutePath(string $kind, int $variantId, string $locale): ?string
    {
        $safeLocale = preg_replace('/[^a-z0-9_-]/i', '', $locale) ?? 'locale';
        $absolute = sprintf(
            '%s/var/employment_documents/%s/%d/%s',
            $this->projectDir,
            $kind,
            $variantId,
            $safeLocale,
        );
        $base = realpath($this->projectDir.'/var/employment_documents');
        $target = realpath($absolute) ?: $absolute;

        if ($base === false || !str_starts_with($target, $base)) {
            return null;
        }

        return is_dir($target) ? $target : $absolute;
    }

    /**
     * @brief Remove stamped PDF cache files from a locale directory.
     *
     * @param string $directory Absolute locale directory path.
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    private function deleteStampedFilesInDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if (!str_starts_with($entry, 'stamped-') || !str_ends_with($entry, '.pdf')) {
                continue;
            }

            $absolute = $directory.'/'.$entry;
            $base = realpath($this->projectDir.'/var/employment_documents');
            $target = realpath($absolute);
            if ($base === false || $target === false || !str_starts_with($target, $base)) {
                continue;
            }

            if (is_file($target)) {
                @unlink($target);
            }
        }
    }

    /**
     * @brief Persist upload under variant locale directory.
     *
     * @param string $kind cv or lm.
     * @param int $variantId Variant id.
     * @param string $locale Locale code.
     * @param string $role template|pdf.
     * @param UploadedFile $upload Upload file.
     * @return string Relative storage path.
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function store(string $kind, int $variantId, string $locale, string $role, UploadedFile $upload): string
    {
        $safeLocale = preg_replace('/[^a-z0-9_-]/i', '', $locale) ?? 'locale';
        $extension = strtolower((string) ($upload->guessExtension() ?: $upload->getClientOriginalExtension() ?: 'bin'));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? 'bin';
        $filename = $role.'.'.$extension;

        $directory = sprintf(
            '%s/var/employment_documents/%s/%d/%s',
            $this->projectDir,
            $kind,
            $variantId,
            $safeLocale,
        );

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('employment.documents.flash.storage_failed');
        }

        $upload->move($directory, $filename);

        return sprintf('var/employment_documents/%s/%d/%s/%s', $kind, $variantId, $safeLocale, $filename);
    }

    /**
     * @brief Validate upload size, validity, and extension.
     *
     * @param UploadedFile $upload Upload file.
     * @param list<string> $allowedExtensions Lowercase extensions without dot.
     * @param bool $pdfOnly When true only application/pdf mime accepted.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function assertUploadValid(UploadedFile $upload, array $allowedExtensions, bool $pdfOnly): void
    {
        if (!$upload->isValid()) {
            throw new \InvalidArgumentException('employment.documents.flash.invalid_file');
        }

        if ($upload->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('employment.documents.flash.file_too_large');
        }

        $extension = strtolower((string) ($upload->guessExtension() ?: $upload->getClientOriginalExtension() ?: ''));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('employment.documents.flash.invalid_file');
        }

        if ($pdfOnly) {
            $mime = (string) $upload->getMimeType();
            if ($mime !== '' && $mime !== 'application/pdf' && $mime !== 'application/x-pdf') {
                throw new \InvalidArgumentException('employment.documents.flash.invalid_file');
            }
        }
    }
}
