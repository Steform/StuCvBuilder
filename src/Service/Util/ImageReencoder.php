<?php

declare(strict_types=1);

namespace App\Service\Util;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @brief Re-encode raster uploads to strip embedded payloads.
 */
final class ImageReencoder
{
    /**
     * @brief Re-encode an uploaded raster image and write it to the target path.
     *
     * @param UploadedFile $uploadedFile Validated upload.
     * @param string $absoluteTargetPath Destination file path including extension.
     * @param string $mimeType Detected MIME type.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function reencodeToPath(UploadedFile $uploadedFile, string $absoluteTargetPath, string $mimeType): void
    {
        $sourcePath = $uploadedFile->getPathname();
        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if ($image === false) {
            throw new \InvalidArgumentException('Invalid image upload');
        }

        $extension = strtolower(pathinfo($absoluteTargetPath, PATHINFO_EXTENSION));
        $saved = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $absoluteTargetPath, 90),
            'png' => imagepng($image, $absoluteTargetPath),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $absoluteTargetPath, 90) : false,
            default => false,
        };
        imagedestroy($image);

        if ($saved !== true) {
            throw new \RuntimeException('Unable to store re-encoded image');
        }
    }
}
