<?php

declare(strict_types=1);

namespace App\Service\Cv;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @brief Store and validate CV flagship project preview uploads (always persisted as WebP).
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class CvFlagshipProjectPreviewUploadService
{
    private const MAX_BYTES = 1048576;

    /**
     * @param string $projectDir Symfony project root directory.
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Store an uploaded project preview and return the relative public WebP path.
     *
     * @param UploadedFile $uploadedFile Validated upload.
     * @param string $projectId Project UUID for filename suffix.
     * @return string Relative path under public/.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function store(UploadedFile $uploadedFile, string $projectId): string
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flagship_projects.flash_invalid_preview');
        }

        if ($uploadedFile->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flagship_projects.flash_invalid_preview');
        }

        $mimeType = (string) $uploadedFile->getMimeType();
        $extension = strtolower((string) ($uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: ''));

        if (!in_array($mimeType, ['image/webp', 'image/png', 'image/jpeg', 'image/gif'], true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flagship_projects.flash_invalid_preview');
        }

        if (!in_array($extension, ['webp', 'png', 'jpg', 'jpeg', 'gif'], true)) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flagship_projects.flash_invalid_preview');
        }

        $targetDirectory = $this->absoluteUploadDirectory();
        $safeId = preg_replace('/[^a-f0-9-]/i', '', $projectId) ?? 'project';
        $safeId = substr($safeId, 0, 32);
        $targetFilename = sprintf('project-%s-%s.webp', substr($safeId, 0, 12), bin2hex(random_bytes(4)));
        $absoluteTarget = $targetDirectory.'/'.$targetFilename;

        if ($mimeType === 'image/webp') {
            $uploadedFile->move($targetDirectory, $targetFilename);

            return FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX.$targetFilename;
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new \RuntimeException('dashboard.customization_cv.flagship_projects.flash_invalid_preview');
        }

        $binary = (string) file_get_contents((string) $uploadedFile->getPathname());
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new \InvalidArgumentException('dashboard.customization_cv.flagship_projects.flash_invalid_preview');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $saved = imagewebp($image, $absoluteTarget, 85);
        imagedestroy($image);
        if (!$saved) {
            throw new \RuntimeException('dashboard.customization_cv.flagship_projects.flash_invalid_preview');
        }

        return FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX.$targetFilename;
    }

    /**
     * @brief Delete a previously stored custom project preview when safe.
     *
     * @param string|null $relativePath Relative path under public/.
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function deleteIfNeeded(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (FlagshipProjectsContract::normalizeStoredPreviewPath($relativePath) === null) {
            return;
        }

        $absolutePath = $this->projectDir.'/public/'.$relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * @return string Absolute upload directory path.
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function absoluteUploadDirectory(): string
    {
        $targetDirectory = rtrim($this->projectDir, '/').'/public/'.rtrim(FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX, '/');
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        return $targetDirectory;
    }
}
