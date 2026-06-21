<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CertificationContract;
use App\Service\Cv\CvCertificationProofUploadService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @brief Unit tests for {@see CvCertificationProofUploadService} PDF storage.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
final class CvCertificationProofUploadServiceTest extends TestCase
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
     * @brief Valid PDF uploads must be stored under the certification custom directory.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testStorePersistsValidPdf(): void
    {
        $this->createProjectDir();
        $pdfPath = $this->createTempPdf();
        $upload = new UploadedFile($pdfPath, 'certificate.pdf', 'application/pdf', null, true);
        $service = new CvCertificationProofUploadService($this->projectDir);
        $entryId = '550e8400-e29b-41d4-a716-446655440000';

        $relativePath = $service->store($upload, $entryId);

        self::assertStringStartsWith(CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX, $relativePath);
        self::assertStringEndsWith('.pdf', $relativePath);
        self::assertFileExists($this->projectDir.'/public/'.$relativePath);
    }

    /**
     * @brief Non-PDF mime types must be rejected before writing to disk.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testStoreRejectsInvalidMimeType(): void
    {
        $this->createProjectDir();
        $tempPath = tempnam(sys_get_temp_dir(), 'cert-proof-');
        self::assertNotFalse($tempPath);
        file_put_contents($tempPath, 'not-a-pdf');

        $upload = new UploadedFile($tempPath, 'proof.txt', 'text/plain', null, true);
        $service = new CvCertificationProofUploadService($this->projectDir);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dashboard.customization_cv.flash.certification_invalid_pdf');
        $service->store($upload, '550e8400-e29b-41d4-a716-446655440000');
    }

    /**
     * @brief Files missing the PDF magic header must be rejected.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testStoreRejectsMissingPdfMagicHeader(): void
    {
        $this->createProjectDir();
        $tempPath = tempnam(sys_get_temp_dir(), 'cert-proof-');
        self::assertNotFalse($tempPath);
        $pdfFile = $tempPath.'.pdf';
        rename($tempPath, $pdfFile);
        file_put_contents($pdfFile, 'application/pdf without header');

        $upload = new UploadedFile($pdfFile, 'fake.pdf', 'application/pdf', null, true);
        $service = new CvCertificationProofUploadService($this->projectDir);

        $this->expectException(\InvalidArgumentException::class);
        $service->store($upload, '550e8400-e29b-41d4-a716-446655440000');
    }

    /**
     * @brief deleteIfStored must remove files under the certification proof prefix only.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testDeleteIfStoredRemovesCustomProofOnly(): void
    {
        $this->createProjectDir();
        $relativePath = CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-test.pdf';
        $absolutePath = $this->projectDir.'/public/'.$relativePath;
        file_put_contents($absolutePath, '%PDF-1.4 test');

        $service = new CvCertificationProofUploadService($this->projectDir);
        $service->deleteIfStored($relativePath);

        self::assertFileDoesNotExist($absolutePath);
        self::assertFileDoesNotExist($this->projectDir.'/public/images/home/dashboard.svg');
    }

    /**
     * @return string Absolute path to a minimal valid PDF fixture.
     */
    private function createTempPdf(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cert-proof-');
        self::assertNotFalse($path);
        $pdfFile = $path.'.pdf';
        rename($path, $pdfFile);
        file_put_contents($pdfFile, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

        return $pdfFile;
    }

    /**
     * @brief Create isolated project directory for filesystem fixtures.
     *
     * @return string
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function createProjectDir(): string
    {
        if ($this->projectDir !== '') {
            return $this->projectDir;
        }

        $base = sys_get_temp_dir().'/cv-cert-proof-upload-'.bin2hex(random_bytes(4));
        mkdir($base.'/public/'.rtrim(CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX, '/'), 0775, true);
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
