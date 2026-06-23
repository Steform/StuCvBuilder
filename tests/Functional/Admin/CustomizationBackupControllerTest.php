<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Cv\CvProfilePersistenceScope;
use App\Entity\CvProfile;
use App\Entity\HomeQuickTile;
use App\Entity\HomeQuickTileTranslation;
use App\Repository\CvProfileRepository;
use App\Repository\HomeCustomizationRepository;
use App\Repository\HomeQuickTileRepository;
use App\Service\Customization\CustomizationAssetScope;
use App\Service\Customization\CustomizationBackupCryptoService;
use App\Service\Customization\CustomizationBackupExportService;
use App\Service\Customization\CustomizationBackupImportService;
use App\Service\Customization\CustomizationBackupManifestBuilder;
use App\Service\Customization\CustomizationBackupPaths;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Customization\CustomizationPreResetBackupWriter;
use App\Service\Customization\CustomizationResetService;
use App\Service\Cv\FlagshipProjectsContract;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use ZipArchive;

final class CustomizationBackupControllerTest extends KernelTestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @brief Ensure backup route is registered.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testRouteIsRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        self::assertNotSame('', $router->generate('app_dashboard_customization_backup'));
    }

    /**
     * @brief Admin menu template must link to customization backup page.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testMenuTemplateContainsBackupRoute(): void
    {
        $menu = @file_get_contents(self::projectRoot().'/templates/components/_admin_dashboard_menu.html.twig') ?: '';
        self::assertStringContainsString("path('app_dashboard_customization_backup')", $menu);
        self::assertStringContainsString('dashboard.customization_backup.menu.label', $menu);
    }

    /**
     * @brief Controller must be admin-only and expose backup route name.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testControllerContract(): void
    {
        $source = @file_get_contents(self::projectRoot().'/src/Controller/CustomizationBackupController.php') ?: '';
        self::assertStringContainsString("#[IsGranted('ROLE_CV_EDIT')]", $source);
        self::assertStringContainsString("name: 'app_dashboard_customization_backup'", $source);
        self::assertStringContainsString('customization_backup_export', $source);
        self::assertStringContainsString('customization_backup_restore', $source);
        self::assertStringContainsString('customization_backup_reset', $source);
        self::assertStringContainsString('delete_snapshot', $source);
        self::assertStringContainsString('CustomizationResetService', $source);
    }

    /**
     * @brief Export service returns encrypted attachment bytes when database schema is available.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testExportProducesEncryptedBlob(): void
    {
        if (!$this->isDatabaseReady()) {
            self::markTestSkipped('Database schema unavailable for customization backup export test.');
        }

        self::bootKernel();
        /** @var CustomizationBackupExportService $exportService */
        $exportService = static::getContainer()->get(CustomizationBackupExportService::class);

        $exported = $exportService->export();
        self::assertStringStartsWith('cbak.v1.', $exported['content']);
        self::assertStringEndsWith('.cvbackup', $exported['filename']);
    }

    /**
     * @brief Export then import round-trip succeeds when database schema is available.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testExportManifestDeclaresFullTreesScope(): void
    {
        if (!$this->isDatabaseReady()) {
            self::markTestSkipped('Database schema unavailable for customization backup export test.');
        }

        self::bootKernel();
        $container = static::getContainer();
        /** @var CustomizationBackupExportService $exportService */
        $exportService = $container->get(CustomizationBackupExportService::class);
        /** @var CustomizationBackupCryptoService $cryptoService */
        $cryptoService = $container->get(CustomizationBackupCryptoService::class);

        $exported = $exportService->export();
        $zipBytes = $cryptoService->decrypt($exported['content']);
        $entries = $this->extractZipEntries($zipBytes);
        $manifestRaw = $entries[CustomizationBackupPaths::MANIFEST] ?? '';
        $manifest = json_decode($manifestRaw, true);

        self::assertIsArray($manifest);
        self::assertSame(CustomizationBackupPaths::FORMAT_VERSION, $manifest['formatVersion'] ?? null);
        self::assertSame(CustomizationAssetScope::FILE_SCOPE_CUSTOMIZABLE_ONLY, $manifest['fileScope'] ?? null);
        self::assertArrayNotHasKey('files/images/home/dashboard.svg', $entries);
        foreach (CustomizationBackupPaths::employmentDataPaths() as $employmentPath) {
            self::assertArrayHasKey($employmentPath, $entries, 'Missing employment backup entry: '.$employmentPath);
        }
    }

    /**
     * @brief Legacy v1 archives without full image trees must still restore successfully.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testLegacyReferencedOnlyArchiveRestores(): void
    {
        if (!$this->isDatabaseReady()) {
            self::markTestSkipped('Database schema unavailable for customization backup restore test.');
        }

        self::bootKernel();
        $container = static::getContainer();
        /** @var CustomizationBackupManifestBuilder $manifestBuilder */
        $manifestBuilder = $container->get(CustomizationBackupManifestBuilder::class);
        /** @var CustomizationBackupCryptoService $cryptoService */
        $cryptoService = $container->get(CustomizationBackupCryptoService::class);
        /** @var CustomizationBackupImportService $importService */
        $importService = $container->get(CustomizationBackupImportService::class);

        $entryContents = [
            CustomizationBackupPaths::DATA_HOME => (string) json_encode([
                'signatureImageRelativePath' => null,
                'backgroundImageRelativePath' => null,
                'introTitleCssSanitized' => null,
                'webcvButtonCssSanitized' => null,
                'webcvButtonCssHoverSanitized' => null,
                'dashboardTileCssSanitized' => 'border-radius: 1rem;',
                'quickTileStyle' => 'style_3',
                'quickTileCssSanitized' => null,
                'dashboardTileIconRelativePath' => 'images/home/custom/quick-tile-dashboard-test.svg',
            ], JSON_THROW_ON_ERROR),
            CustomizationBackupPaths::DATA_HOME_TRANSLATIONS => '[]',
            CustomizationBackupPaths::DATA_CV_PROFILE => (string) json_encode([
                'title' => 'CV',
                'contentJson' => [],
            ], JSON_THROW_ON_ERROR),
            CustomizationBackupPaths::DATA_LOCALE => (string) json_encode([
                'active_locales' => ['fr', 'en'],
                'default_locale' => 'fr',
            ], JSON_THROW_ON_ERROR),
        ];
        foreach (CustomizationBackupPaths::employmentDataPaths() as $employmentPath) {
            $entryContents[$employmentPath] = '[]';
        }

        $manifest = $manifestBuilder->build($entryContents, 'test');
        $entryContents[CustomizationBackupPaths::MANIFEST] = (string) json_encode($manifest, JSON_THROW_ON_ERROR);
        $zipBytes = $this->createZipFromEntries($entryContents);
        $encrypted = $cryptoService->encrypt($zipBytes);

        $importService->restoreFromEncryptedBlob($encrypted);

        /** @var HomeCustomizationRepository $homeRepository */
        $homeRepository = $container->get(HomeCustomizationRepository::class);
        $home = $homeRepository->findOneBy([]);
        self::assertNotNull($home);
        self::assertSame('style_3', $home->getQuickTileStyle());
        self::assertSame('images/home/custom/quick-tile-dashboard-test.svg', $home->getDashboardTileIconRelativePath());
    }

    /**
     * @brief Export then import round-trip succeeds when database schema is available.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    /**
     * @brief Backup page template exposes reset UI strings.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testBackupTemplateContainsResetSection(): void
    {
        $twig = @file_get_contents(self::projectRoot().'/templates/admin/customization/backup.html.twig') ?: '';
        self::assertStringContainsString('name="action" value="reset"', $twig);
        self::assertStringContainsString('customization_backup_reset', $twig);
        self::assertStringContainsString('dashboard.customization_backup.reset.submit', $twig);
        self::assertStringContainsString('delete_snapshot', $twig);
    }

    /**
     * @brief Reset wipes CV profiles, keeps one home row, writes pre-reset snapshot, and restore recovers data.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testResetPreBackupRoundTrip(): void
    {
        if (!$this->isDatabaseReady()) {
            self::markTestSkipped('Database schema unavailable for customization reset test.');
        }

        self::bootKernel();
        $container = static::getContainer();
        /** @var CustomizationBackupExportService $exportService */
        $exportService = $container->get(CustomizationBackupExportService::class);
        /** @var CustomizationResetService $resetService */
        $resetService = $container->get(CustomizationResetService::class);
        /** @var CustomizationBackupImportService $importService */
        $importService = $container->get(CustomizationBackupImportService::class);
        /** @var CustomizationPreResetBackupWriter $preResetWriter */
        $preResetWriter = $container->get(CustomizationPreResetBackupWriter::class);
        /** @var CvProfileRepository $cvProfileRepository */
        $cvProfileRepository = $container->get(CvProfileRepository::class);
        /** @var HomeCustomizationRepository $homeRepository */
        $homeRepository = $container->get(HomeCustomizationRepository::class);
        /** @var CustomizationPlaceholderStateService $placeholderState */
        $placeholderState = $container->get(CustomizationPlaceholderStateService::class);

        $projectDir = self::projectRoot();
        $dashboardSvg = $projectDir.'/public/images/home/dashboard.svg';
        if (!is_file($dashboardSvg)) {
            if (!is_dir(dirname($dashboardSvg))) {
                mkdir(dirname($dashboardSvg), 0775, true);
            }
            file_put_contents($dashboardSvg, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        }

        $customUpload = $projectDir.'/public/images/home/custom/reset-test.bin';
        if (!is_dir(dirname($customUpload))) {
            mkdir(dirname($customUpload), 0775, true);
        }
        file_put_contents($customUpload, 'custom-data');

        $exported = $exportService->export();
        $snapshotBasename = $resetService->reset();

        self::assertFileExists($dashboardSvg);
        self::assertFileDoesNotExist($customUpload);
        self::assertSame(0, $cvProfileRepository->count([]));
        self::assertSame(1, $homeRepository->count([]));
        self::assertTrue($placeholderState->isActive());
        self::assertFileExists($preResetWriter->getSnapshotDirectory().'/'.$snapshotBasename);

        $snapshotBytes = file_get_contents($preResetWriter->getSnapshotDirectory().'/'.$snapshotBasename);
        self::assertIsString($snapshotBytes);
        $importService->restoreFromEncryptedBlob($snapshotBytes);

        self::assertGreaterThan(0, $cvProfileRepository->count([]));
        self::assertFalse($placeholderState->isActive());
    }

    public function testExportImportRoundTrip(): void
    {
        if (!$this->isDatabaseReady()) {
            self::markTestSkipped('Database schema unavailable for customization backup round-trip test.');
        }

        self::bootKernel();
        $container = static::getContainer();
        /** @var CustomizationBackupExportService $exportService */
        $exportService = $container->get(CustomizationBackupExportService::class);
        /** @var CustomizationBackupImportService $importService */
        $importService = $container->get(CustomizationBackupImportService::class);

        $exported = $exportService->export();
        $importService->restoreFromEncryptedBlob($exported['content']);

        $reExported = $exportService->export();
        self::assertStringStartsWith('cbak.v1.', $reExported['content']);
        self::assertNotSame('', $reExported['filename']);
    }

    /**
     * @brief Export and import must preserve custom quick tiles and their icon files.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testExportImportRoundTripPreservesCustomQuickTiles(): void
    {
        if (!$this->isDatabaseReady()) {
            self::markTestSkipped('Database schema unavailable for customization backup quick tile test.');
        }

        self::bootKernel();
        $container = static::getContainer();
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine')->getManager();
        /** @var HomeCustomizationRepository $homeRepository */
        $homeRepository = $container->get(HomeCustomizationRepository::class);
        /** @var HomeQuickTileRepository $quickTileRepository */
        $quickTileRepository = $container->get(HomeQuickTileRepository::class);
        /** @var CustomizationBackupExportService $exportService */
        $exportService = $container->get(CustomizationBackupExportService::class);
        /** @var CustomizationBackupImportService $importService */
        $importService = $container->get(CustomizationBackupImportService::class);

        $home = $homeRepository->findOneBy([]);
        self::assertNotNull($home);

        $iconRelativePath = 'images/home/custom/backup-quick-tile-test.svg';
        $iconAbsolutePath = self::projectRoot().'/public/'.$iconRelativePath;
        if (!is_dir(dirname($iconAbsolutePath))) {
            mkdir(dirname($iconAbsolutePath), 0775, true);
        }
        file_put_contents($iconAbsolutePath, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $tile = new HomeQuickTile();
        $tile->setCustomization($home);
        $tile->setLinkUrl('https://example.com/backup-quick-tile');
        $tile->setOpenInNewTab(true);
        $tile->setEnabled(true);
        $tile->setSortOrder(42);
        $tile->setIconRelativePath($iconRelativePath);

        $translation = new HomeQuickTileTranslation();
        $translation->setLocale('fr');
        $translation->setLabel('Tuile backup test');
        $tile->addTranslation($translation);

        $entityManager->persist($tile);
        $entityManager->flush();

        $exported = $exportService->export();
        $quickTileRepository->deleteAllForCustomization($home);
        $entityManager->flush();
        if (is_file($iconAbsolutePath)) {
            unlink($iconAbsolutePath);
        }
        self::assertFileDoesNotExist($iconAbsolutePath);

        $entityManager->clear();
        $importService->restoreFromEncryptedBlob($exported['content']);

        $restoredTiles = $quickTileRepository->findAllOrdered($home);
        self::assertCount(1, $restoredTiles);
        $restored = $restoredTiles[0];
        self::assertSame('https://example.com/backup-quick-tile', $restored->getLinkUrl());
        self::assertTrue($restored->isOpenInNewTab());
        self::assertTrue($restored->isEnabled());
        self::assertSame($iconRelativePath, $restored->getIconRelativePath());
        self::assertSame('Tuile backup test', $restored->getLabelForLocale('fr'));
        self::assertFileExists($iconAbsolutePath);
    }

    /**
     * @brief Export and import must preserve flagship project JSON and custom preview files.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testExportImportRoundTripPreservesFlagshipProjectPreview(): void
    {
        if (!$this->isDatabaseReady()) {
            self::markTestSkipped('Database schema unavailable for customization backup flagship project test.');
        }

        self::bootKernel();
        $container = static::getContainer();
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine')->getManager();
        /** @var CvProfileRepository $cvProfileRepository */
        $cvProfileRepository = $container->get(CvProfileRepository::class);
        /** @var CustomizationBackupExportService $exportService */
        $exportService = $container->get(CustomizationBackupExportService::class);
        /** @var CustomizationBackupImportService $importService */
        $importService = $container->get(CustomizationBackupImportService::class);

        $projectId = FlagshipProjectsContract::generateUuidV4();
        $previewRelativePath = 'images/cv/projects/custom/project-backup-roundtrip.webp';
        $previewAbsolutePath = self::projectRoot().'/public/'.$previewRelativePath;
        if (!is_dir(dirname($previewAbsolutePath))) {
            mkdir(dirname($previewAbsolutePath), 0775, true);
        }
        file_put_contents($previewAbsolutePath, 'RIFF....WEBP');

        $contentJson = CvProfilePersistenceScope::sanitizeForPersistence([
            FlagshipProjectsContract::KEY_SECTION_ENABLED => true,
            FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => $projectId,
                    'sortOrder' => 0,
                    'title' => 'Backup flagship',
                    'description' => 'Round-trip test',
                    'tags' => ['Symfony'],
                    'previewAlt' => 'Preview',
                    'previewImagePath' => $previewRelativePath,
                    'githubUrl' => 'https://github.com/example/backup-flagship',
                    'demoUrl' => 'https://example.com/demo/',
                    'isVisible' => true,
                ]],
            ],
        ]);

        foreach ($cvProfileRepository->findAll() as $existingProfile) {
            $entityManager->remove($existingProfile);
        }
        $entityManager->flush();

        $profile = new CvProfile('backup-flagship-test', (string) json_encode($contentJson, JSON_THROW_ON_ERROR));
        $entityManager->persist($profile);
        $entityManager->flush();

        $exported = $exportService->export();

        $entityManager->remove($profile);
        $entityManager->flush();
        if (is_file($previewAbsolutePath)) {
            unlink($previewAbsolutePath);
        }
        self::assertFileDoesNotExist($previewAbsolutePath);

        $entityManager->clear();
        $importService->restoreFromEncryptedBlob($exported['content']);

        $restoredProfile = $cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(CvProfile::class, $restoredProfile);
        $decoded = json_decode($restoredProfile->getContentJson(), true);
        self::assertIsArray($decoded);
        self::assertSame(
            'Backup flagship',
            $decoded[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['title'] ?? null
        );
        self::assertSame($previewRelativePath, $decoded[FlagshipProjectsContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['previewImagePath'] ?? null);
        self::assertFileExists($previewAbsolutePath);
    }

    /**
     * @brief Check whether customization tables exist for integration tests.
     *
     * @return bool
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function isDatabaseReady(): bool
    {
        try {
            self::bootKernel();
            static::getContainer()->get('doctrine.dbal.default_connection')
                ->executeQuery('SELECT 1 FROM home_customization LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @brief Extract ZIP members into an in-memory map.
     *
     * @param string $zipBytes ZIP binary payload.
     * @return array<string, string>
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function extractZipEntries(string $zipBytes): array
    {
        $tempZip = tempnam(sys_get_temp_dir(), 'cv-custom-test-');
        self::assertNotFalse($tempZip);
        $zipPath = $tempZip.'.zip';
        @unlink($tempZip);
        file_put_contents($zipPath, $zipBytes);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath));
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || str_ends_with($name, '/')) {
                continue;
            }

            $content = $zip->getFromIndex($index);
            if ($content !== false) {
                $entries[$name] = $content;
            }
        }

        $zip->close();
        @unlink($zipPath);

        return $entries;
    }

    /**
     * @brief Build a ZIP archive from entry map.
     *
     * @param array<string, string> $entryContents Map of entry path to raw bytes.
     * @return string ZIP binary payload.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function createZipFromEntries(array $entryContents): string
    {
        $tempZip = tempnam(sys_get_temp_dir(), 'cv-custom-test-');
        self::assertNotFalse($tempZip);
        $zipPath = $tempZip.'.zip';
        @unlink($tempZip);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entryContents as $path => $bytes) {
            self::assertTrue($zip->addFromString($path, $bytes));
        }
        $zip->close();

        $zipBytes = file_get_contents($zipPath);
        @unlink($zipPath);
        self::assertIsString($zipBytes);

        return $zipBytes;
    }
}
