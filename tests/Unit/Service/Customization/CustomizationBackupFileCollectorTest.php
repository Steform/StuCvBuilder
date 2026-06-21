<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Entity\HomeCustomization;
use App\Service\Customization\CustomizationAssetScope;
use App\Service\Customization\CustomizationBackupFileCollector;
use App\Service\Cv\FlagshipProjectsContract;
use PHPUnit\Framework\TestCase;

final class CustomizationBackupFileCollectorTest extends TestCase
{
    private string $tempProjectDir = '';

    protected function tearDown(): void
    {
        if ($this->tempProjectDir !== '' && is_dir($this->tempProjectDir)) {
            $this->removeDirectory($this->tempProjectDir);
        }

        parent::tearDown();
    }

    /**
     * @brief Ensure CV JSON paths and home image fields are collected.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testCollectsReferencedPaths(): void
    {
        $collector = new CustomizationBackupFileCollector($this->createTempProjectDir());

        $home = new HomeCustomization();
        $home->setSignatureImageRelativePath('images/home/custom/signature.webp');
        $home->setBackgroundImageRelativePath('images/home/custom/background.webp');
        $home->setDashboardTileIconRelativePath('images/home/custom/quick-tile-dashboard-abc.svg');
        $home->setSiteFaviconRelativePath('favicon/custom/site-favicon-abc.svg');

        $cvPaths = $collector->collectFromCvContent([
            'aboutProfilePhotoPath' => 'images/cv/about/custom/photo.webp',
            'experience' => [
                ['logoPath' => 'images/cv/experience/custom/logo.png'],
            ],
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'previewImagePath' => 'images/cv/projects/custom/project-backup-test.webp',
                ]],
            ],
            'proofPdfPath' => 'documents/cv/certification/custom/certification-proof-test.pdf',
            'ignored' => 'https://example.com/images/cv/about/x.webp',
        ]);

        $paths = $collector->mergePaths(
            $collector->collectFromHome($home),
            $cvPaths
        );

        self::assertContains('images/home/custom/signature.webp', $paths);
        self::assertContains('images/home/custom/background.webp', $paths);
        self::assertContains('images/home/custom/quick-tile-dashboard-abc.svg', $paths);
        self::assertContains('favicon/custom/site-favicon-abc.svg', $paths);
        self::assertContains('images/cv/about/custom/photo.webp', $paths);
        self::assertContains('images/cv/experience/custom/logo.png', $paths);
        self::assertContains('images/cv/projects/custom/project-backup-test.webp', $paths);
        self::assertContains('documents/cv/certification/custom/certification-proof-test.pdf', $paths);
        self::assertNotContains('https://example.com/images/cv/about/x.webp', $paths);
    }

    /**
     * @brief Export merge must exclude protected system assets referenced in JSON.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testMergeExportablePathsExcludesProtected(): void
    {
        $collector = new CustomizationBackupFileCollector($this->createTempProjectDir());

        $home = new HomeCustomization();
        $home->setBackgroundImageRelativePath('images/home/dashboard.svg');

        $paths = $collector->mergeExportablePaths(
            $collector->collectFromHome($home),
            ['images/home/custom/only.webp'],
        );

        self::assertContains('images/home/custom/only.webp', $paths);
        self::assertNotContains('images/home/dashboard.svg', $paths);
    }

    /**
     * @brief Custom tree scan must include only customizable upload directories.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testCollectCustomizableImageTreesIgnoresProtectedRoots(): void
    {
        $projectDir = $this->createTempProjectDir();
        $this->writeFile($projectDir.'/public/images/cv/about/orphan.webp', 'cv-orphan');
        $this->writeFile($projectDir.'/public/images/home/custom/orphan.webp', 'home-orphan');
        $this->writeFile($projectDir.'/public/images/home/dashboard.svg', 'dashboard');

        $collector = new CustomizationBackupFileCollector($projectDir);
        $paths = $collector->collectCustomizableImageTrees();

        self::assertContains('images/home/custom/orphan.webp', $paths);
        self::assertNotContains('images/cv/about/orphan.webp', $paths);
        self::assertNotContains('images/home/dashboard.svg', $paths);
    }

    /**
     * @brief Directory tree collector must reject non-customizable roots.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testCollectDirectoryTreeRejectsNonCustomizableRoot(): void
    {
        $projectDir = $this->createTempProjectDir();
        $this->writeFile($projectDir.'/public/images/home/dashboard.svg', 'dashboard');
        $this->writeFile($projectDir.'/public/uploads/secret.txt', 'secret');

        $collector = new CustomizationBackupFileCollector($projectDir);

        self::assertSame([], $collector->collectDirectoryTree('images/home'));
        self::assertSame([], $collector->collectDirectoryTree('uploads'));
        $this->writeFile($projectDir.'/public/images/home/custom/allowed.webp', 'allowed');
        self::assertContains('images/home/custom/allowed.webp', $collector->collectDirectoryTree('images/home/custom'));
    }

    /**
     * @brief Purge must remove customizable uploads but preserve system assets.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testPurgeRemovesOnlyCustomDirectories(): void
    {
        $projectDir = $this->createTempProjectDir();
        foreach (CustomizationAssetScope::getPurgeableDirectoryRoots() as $root) {
            $this->writeFile($projectDir.'/public/'.$root.'/upload.bin', 'custom');
        }

        $this->writeFile($projectDir.'/public/images/home/dashboard.svg', 'dashboard');
        $this->writeFile($projectDir.'/public/images/home/plus.svg', 'plus');
        $this->writeFile($projectDir.'/public/favicon/favicon.svg', 'favicon');
        $this->writeFile($projectDir.'/public/images/cv/about/user-placeholder.webp', 'placeholder');

        $collector = new CustomizationBackupFileCollector($projectDir);
        $collector->purgeCustomizableImageDirectories();

        self::assertFileDoesNotExist($projectDir.'/public/images/home/custom/upload.bin');
        self::assertFileDoesNotExist($projectDir.'/public/images/cv/about/custom/upload.bin');
        self::assertFileDoesNotExist($projectDir.'/public/images/cv/experience/custom/upload.bin');
        self::assertFileDoesNotExist($projectDir.'/public/images/cv/projects/custom/upload.bin');
        self::assertFileDoesNotExist($projectDir.'/public/favicon/custom/upload.bin');
        self::assertFileExists($projectDir.'/public/images/home/dashboard.svg');
        self::assertFileExists($projectDir.'/public/images/home/plus.svg');
        self::assertFileExists($projectDir.'/public/favicon/favicon.svg');
        self::assertFileExists($projectDir.'/public/images/cv/about/user-placeholder.webp');
        self::assertDirectoryExists($projectDir.'/public/images/home/custom');
    }

    /**
     * @brief Create isolated project directory for filesystem fixtures.
     *
     * @param void No input parameter.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function createTempProjectDir(): string
    {
        if ($this->tempProjectDir !== '') {
            return $this->tempProjectDir;
        }

        $base = sys_get_temp_dir().'/cv-backup-collector-'.bin2hex(random_bytes(4));
        mkdir($base.'/public/images/cv/about/custom', 0775, true);
        mkdir($base.'/public/images/cv/experience/custom', 0775, true);
        mkdir($base.'/public/images/cv/projects/custom', 0775, true);
        mkdir($base.'/public/images/home/custom', 0775, true);
        mkdir($base.'/public/favicon/custom', 0775, true);
        $this->tempProjectDir = $base;

        return $this->tempProjectDir;
    }

    /**
     * @brief Write a file under the temp project tree.
     *
     * @param string $absolutePath Target absolute path.
     * @param string $contents File contents.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function writeFile(string $absolutePath, string $contents): void
    {
        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($absolutePath, $contents);
    }

    /**
     * @brief Remove a directory recursively.
     *
     * @param string $directory Absolute directory path.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
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
