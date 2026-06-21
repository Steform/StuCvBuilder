<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @brief Store and validate CV interest icon uploads (PNG/WebP conversion, SVG, WebP).
 *
 * @date 2026-06-10
 * @author Stephane H.
 */
final class CvInterestsIconUploadService
{
    private const MAX_BYTES = 512000;

    private const MAX_SVG_BYTES = 120000;

    /**
     * @param string $projectDir Symfony project root directory.
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Store an uploaded interest icon and return the relative public path.
     *
     * @param UploadedFile $uploadedFile Validated upload.
     * @param string $entryId Interest entry UUID for filename suffix.
     * @return string Relative path under public/.
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function store(UploadedFile $uploadedFile, string $entryId): string
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        if ($uploadedFile->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        $mimeType = (string) $uploadedFile->getMimeType();
        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));

        $targetDirectory = $this->absoluteUploadDirectory();
        $safeId = preg_replace('/[^a-z0-9-]/i', '', $entryId) ?? 'interest';
        $safeId = substr($safeId, 0, 32);

        if ($mimeType === 'image/svg+xml' || $extension === 'svg') {
            return $this->storeSvg($uploadedFile, $targetDirectory, $safeId);
        }

        if (in_array($mimeType, ['image/webp', 'image/png', 'image/jpeg'], true)) {
            return $this->storeRasterAsWebp($uploadedFile, $targetDirectory, $safeId, $mimeType);
        }

        throw new \InvalidArgumentException('dashboard.customization_cv.interests.flash_invalid_icon');
    }

    /**
     * @brief Delete a previously stored custom interest icon when safe.
     *
     * @param string|null $relativePath Relative path under public/.
     * @return void
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function deleteIfNeeded(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (InterestsContract::normalizeIconPath($relativePath) === null) {
            return;
        }

        $absolutePath = $this->projectDir.'/public/'.$relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * @return string
     */
    private function absoluteUploadDirectory(): string
    {
        $targetDirectory = rtrim($this->projectDir, '/').'/public/'.rtrim(InterestsContract::INTEREST_ICON_PATH_PREFIX, '/');
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        return $targetDirectory;
    }

    /**
     * @param UploadedFile $uploadedFile SVG upload.
     * @param string $targetDirectory Absolute directory.
     * @param string $safeId Entry id fragment.
     * @return string Relative path.
     */
    private function storeSvg(UploadedFile $uploadedFile, string $targetDirectory, string $safeId): string
    {
        $contents = (string) file_get_contents((string) $uploadedFile->getPathname());
        if ($contents === '' || strlen($contents) > self::MAX_SVG_BYTES) {
            throw new \InvalidArgumentException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        if (preg_match('/<script|onload=|onerror=|javascript:/i', $contents)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        $targetFilename = sprintf('interest-%s-%s.svg', substr($safeId, 0, 12), bin2hex(random_bytes(4)));
        $absoluteTarget = $targetDirectory.'/'.$targetFilename;
        if (file_put_contents($absoluteTarget, $contents) === false) {
            throw new \RuntimeException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        return InterestsContract::INTEREST_ICON_PATH_PREFIX.$targetFilename;
    }

    /**
     * @param UploadedFile $uploadedFile Raster upload.
     * @param string $targetDirectory Absolute directory.
     * @param string $safeId Entry id fragment.
     * @param string $mimeType Detected mime type.
     * @return string Relative path.
     */
    private function storeRasterAsWebp(UploadedFile $uploadedFile, string $targetDirectory, string $safeId, string $mimeType): string
    {
        $targetFilename = sprintf('interest-%s-%s.webp', substr($safeId, 0, 12), bin2hex(random_bytes(4)));
        $absoluteTarget = $targetDirectory.'/'.$targetFilename;

        if ($mimeType === 'image/webp') {
            $uploadedFile->move($targetDirectory, $targetFilename);

            return InterestsContract::INTEREST_ICON_PATH_PREFIX.$targetFilename;
        }

        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        $binary = (string) file_get_contents((string) $uploadedFile->getPathname());
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new \InvalidArgumentException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        if (!function_exists('imagewebp')) {
            imagedestroy($image);
            throw new \RuntimeException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        $saved = imagewebp($image, $absoluteTarget, 85);
        imagedestroy($image);
        if (!$saved) {
            throw new \RuntimeException('dashboard.customization_cv.interests.flash_invalid_icon');
        }

        return InterestsContract::INTEREST_ICON_PATH_PREFIX.$targetFilename;
    }
}
