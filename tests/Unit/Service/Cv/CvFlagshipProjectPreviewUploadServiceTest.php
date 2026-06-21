<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CvFlagshipProjectPreviewUploadService;
use App\Service\Cv\FlagshipProjectsContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @brief Unit tests for {@see CvFlagshipProjectPreviewUploadService} raster conversion.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class CvFlagshipProjectPreviewUploadServiceTest extends TestCase
{
    private string $projectDir = '';

    protected function tearDown(): void
    {
        if ($this->projectDir !== '' && is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }

        parent::tearDown();
    }

    /**
     * @brief PNG uploads must be stored as WebP under the customizable projects directory.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testStoreConvertsPngToWebp(): void
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
            self::markTestSkipped('GD WebP support is required for flagship preview upload tests.');
        }

        $this->createProjectDir();
        $pngPath = $this->createTempPng();
        $upload = new UploadedFile($pngPath, 'preview.png', 'image/png', null, true);
        $service = new CvFlagshipProjectPreviewUploadService($this->projectDir);
        $projectId = FlagshipProjectsContract::generateUuidV4();

        $relativePath = $service->store($upload, $projectId);

        self::assertStringStartsWith(FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX, $relativePath);
        self::assertStringEndsWith('.webp', $relativePath);
        self::assertFileExists($this->projectDir.'/public/'.$relativePath);
    }

    /**
     * @brief WebP uploads must be moved without re-encoding when already WebP.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testStoreKeepsValidWebpUpload(): void
    {
        if (!function_exists('imagewebp')) {
            self::markTestSkipped('GD WebP support is required for flagship preview upload tests.');
        }

        $this->createProjectDir();
        $webpPath = $this->createTempWebp();
        $upload = new UploadedFile($webpPath, 'preview.webp', 'image/webp', null, true);
        $service = new CvFlagshipProjectPreviewUploadService($this->projectDir);
        $projectId = FlagshipProjectsContract::generateUuidV4();

        $relativePath = $service->store($upload, $projectId);

        self::assertStringEndsWith('.webp', $relativePath);
        self::assertFileExists($this->projectDir.'/public/'.$relativePath);
    }

    /**
     * @brief Invalid mime types must be rejected before writing to disk.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testStoreRejectsInvalidMimeType(): void
    {
        $this->createProjectDir();
        $tempPath = tempnam(sys_get_temp_dir(), 'flagship-preview-');
        self::assertNotFalse($tempPath);
        file_put_contents($tempPath, 'not-an-image');

        $upload = new UploadedFile($tempPath, 'preview.txt', 'text/plain', null, true);
        $service = new CvFlagshipProjectPreviewUploadService($this->projectDir);

        $this->expectException(\InvalidArgumentException::class);
        $service->store($upload, FlagshipProjectsContract::generateUuidV4());
    }

    /**
     * @return string Absolute path to a temporary PNG file.
     */
    private function createTempPng(): string
    {
        $image = imagecreatetruecolor(4, 4);
        $red = imagecolorallocate($image, 200, 40, 40);
        imagefilledrectangle($image, 0, 0, 3, 3, $red);
        $path = tempnam(sys_get_temp_dir(), 'flagship-png-');
        self::assertNotFalse($path);
        $pngFile = $path.'.png';
        rename($path, $pngFile);
        imagepng($image, $pngFile);
        imagedestroy($image);

        return $pngFile;
    }

    /**
     * @return string Absolute path to a temporary WebP file.
     */
    private function createTempWebp(): string
    {
        $image = imagecreatetruecolor(4, 4);
        $blue = imagecolorallocate($image, 40, 80, 200);
        imagefilledrectangle($image, 0, 0, 3, 3, $blue);
        $path = tempnam(sys_get_temp_dir(), 'flagship-webp-');
        self::assertNotFalse($path);
        $webpFile = $path.'.webp';
        rename($path, $webpFile);
        imagewebp($image, $webpFile, 85);
        imagedestroy($image);

        return $webpFile;
    }

    /**
     * @brief Create isolated project directory for filesystem fixtures.
     *
     * @return string
     */
    private function createProjectDir(): string
    {
        if ($this->projectDir !== '') {
            return $this->projectDir;
        }

        $base = sys_get_temp_dir().'/cv-flagship-upload-'.bin2hex(random_bytes(4));
        mkdir($base.'/public/'.rtrim(FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX, '/'), 0775, true);
        $this->projectDir = $base;

        return $this->projectDir;
    }

    /**
     * @param string $directory Absolute directory path.
     * @return void
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
