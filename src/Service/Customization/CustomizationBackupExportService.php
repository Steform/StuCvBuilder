<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Cv\CvProfilePersistenceScope;
use App\Entity\HomeCustomization;
use App\Entity\HomeCustomizationTranslation;
use App\Repository\CvProfileRepository;
use App\Service\Home\HomeCustomizationService;
use App\Service\Home\HomeQuickTileService;
use App\Service\Locale\LocaleConfigurationService;
use App\Exception\Customization\CustomizationBackupException;
use ZipArchive;

/**
 * @brief Build encrypted customization backup blobs from live site configuration.
 */
final class CustomizationBackupExportService
{
    /**
     * @param string $projectDir Symfony project root directory.
     * @param string $appVersion Application version label.
     * @param int $maxExportBytes Maximum total payload size before export is rejected.
     */
    public function __construct(
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly HomeQuickTileService $homeQuickTileService,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CustomizationBackupFileCollector $fileCollector,
        private readonly CustomizationBackupManifestBuilder $manifestBuilder,
        private readonly CustomizationBackupCryptoService $cryptoService,
        private readonly CustomizationEmploymentBackupService $employmentBackupService,
        private readonly string $projectDir,
        private readonly string $appVersion,
        private readonly int $maxExportBytes,
    ) {
    }

    /**
     * @brief Export current customization into an encrypted backup blob.
     *
     * @return array{filename: string, content: string} Suggested filename and encrypted bytes.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function export(): array
    {
        if (!$this->cryptoService->isConfigured()) {
            throw CustomizationBackupException::withReason('key_missing');
        }

        $zipBytes = $this->buildZipBytes();
        $encrypted = $this->cryptoService->encrypt($zipBytes);
        $filename = sprintf('customization-backup-%s.cvbackup', (new \DateTimeImmutable())->format('Ymd-His'));

        return [
            'filename' => $filename,
            'content' => $encrypted,
        ];
    }

    /**
     * @brief Assemble ZIP archive bytes for customization data and referenced files.
     *
     * @return string Raw ZIP binary payload.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function buildZipBytes(): string
    {
        $home = $this->homeCustomizationService->getOrCreateSingleton();
        $homePayload = $this->serializeHome($home);
        $translationsPayload = $this->serializeHomeTranslations($home);
        $cvPayload = $this->serializeCvProfile();
        $localePayload = $this->serializeLocaleConfiguration();

        $filePaths = $this->fileCollector->mergeExportablePaths(
            $this->fileCollector->collectFromHome($home),
            $this->homeQuickTileService->collectIconPaths($home),
            $this->fileCollector->collectFromCvContent($cvPayload['contentJson'] ?? []),
            $this->fileCollector->collectFromCvContent(
                $this->employmentBackupService->collectSectionOverrideContentPayloadsForExport(),
            ),
            $this->fileCollector->collectCustomizableImageTrees(),
        );

        $entryContents = [
            CustomizationBackupPaths::DATA_HOME => $this->encodeJson($homePayload),
            CustomizationBackupPaths::DATA_HOME_TRANSLATIONS => $this->encodeJson($translationsPayload),
            CustomizationBackupPaths::DATA_CV_PROFILE => $this->encodeJson($cvPayload),
            CustomizationBackupPaths::DATA_LOCALE => $this->encodeJson($localePayload),
        ];
        $entryContents = array_merge($entryContents, $this->employmentBackupService->buildJsonEntries());

        $employmentFilePaths = $this->employmentBackupService->collectStorageFilePaths();
        $publicDir = $this->projectDir.'/public';
        $this->assertExportSizeWithinLimit($entryContents, $filePaths, $publicDir, $employmentFilePaths);

        foreach ($filePaths as $relativePath) {
            $absolute = $publicDir.'/'.$relativePath;
            if (!is_file($absolute)) {
                continue;
            }

            $bytes = file_get_contents($absolute);
            if ($bytes === false) {
                throw CustomizationBackupException::withReason('export_failed');
            }

            $entryContents[CustomizationBackupPaths::FILES_PREFIX.$relativePath] = $bytes;
        }

        foreach ($employmentFilePaths as $relativePath) {
            $absolute = $this->projectDir.'/'.$relativePath;
            if (!is_file($absolute)) {
                continue;
            }

            $bytes = file_get_contents($absolute);
            if ($bytes === false) {
                throw CustomizationBackupException::withReason('export_failed');
            }

            $entryContents[CustomizationBackupPaths::EMPLOYMENT_FILES_PREFIX.$relativePath] = $bytes;
        }

        $manifest = $this->manifestBuilder->build(
            $entryContents,
            $this->appVersion,
            CustomizationAssetScope::FILE_SCOPE_CUSTOMIZABLE_ONLY,
        );
        $entryContents[CustomizationBackupPaths::MANIFEST] = $this->encodeJson($manifest);

        return $this->createZipFromEntries($entryContents);
    }

    /**
     * @brief Serialize scalar home customization fields.
     *
     * @param HomeCustomization $home Home customization entity.
     * @return array<string, string|null>
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function serializeHome(HomeCustomization $home): array
    {
        return [
            'signatureImageRelativePath' => $home->getSignatureImageRelativePath(),
            'backgroundImageRelativePath' => $home->getBackgroundImageRelativePath(),
            'introTitleCssSanitized' => $home->getIntroTitleCssSanitized(),
            'webcvButtonCssSanitized' => $home->getWebcvButtonCssSanitized(),
            'webcvButtonCssHoverSanitized' => $home->getWebcvButtonCssHoverSanitized(),
            'backgroundCssSanitized' => $home->getBackgroundCssSanitized(),
            'signatureCssSanitized' => $home->getSignatureCssSanitized(),
            'quickTileStyle' => $home->getQuickTileStyle(),
            'quickTileCssSanitized' => $home->getQuickTileCssSanitized(),
            'dashboardTileIconRelativePath' => $home->getDashboardTileIconRelativePath(),
            'siteFaviconRelativePath' => $home->getSiteFaviconRelativePath(),
            'openGraphImageRelativePath' => $home->getOpenGraphImageRelativePath(),
            'cvAntibotThreshold' => $home->getCvAntibotThreshold(),
            'maintenanceModeEnabled' => $home->isMaintenanceModeEnabled(),
            'siteColorsJson' => $home->getSiteColorsJson(),
            'mailTemplatesJson' => $home->getMailTemplatesJson(),
            'quickTiles' => $this->homeQuickTileService->serializeForBackup($home),
        ];
    }

    /**
     * @brief Serialize home intro translations without Doctrine identifiers.
     *
     * @param HomeCustomization $home Home customization entity.
     * @return list<array{locale: string, introText: string, metaDescription: string}>
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function serializeHomeTranslations(HomeCustomization $home): array
    {
        /** @var array<string, array{locale: string, introText: string, metaDescription: string}> $byLocale */
        $byLocale = [];
        foreach ($home->getTranslations() as $translation) {
            if (!$translation instanceof HomeCustomizationTranslation) {
                continue;
            }

            $locale = trim($translation->getLocale());
            if ($locale === '') {
                continue;
            }

            $byLocale[strtolower($locale)] = [
                'locale' => $locale,
                'introText' => $translation->getIntroText(),
                'metaDescription' => $translation->getMetaDescription(),
            ];
        }

        $rows = array_values($byLocale);
        usort($rows, static fn (array $a, array $b): int => strcmp($a['locale'], $b['locale']));

        return $rows;
    }

    /**
     * @brief Serialize latest CV profile or empty defaults with persisted-key whitelist applied.
     *
     * @return array{title: string, contentJson: array<string, mixed>}
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function serializeCvProfile(): array
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if ($profile === null) {
            return [
                'title' => 'CV',
                'contentJson' => [],
            ];
        }

        $decoded = json_decode($profile->getContentJson(), true);
        $contentJson = is_array($decoded) ? CvProfilePersistenceScope::sanitizeForPersistence($decoded) : [];

        return [
            'title' => $profile->getTitle(),
            'contentJson' => $contentJson,
        ];
    }

    /**
     * @brief Serialize locale configuration in persisted JSON shape.
     *
     * @return array{active_locales: list<string>, default_locale: string}
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function serializeLocaleConfiguration(): array
    {
        $configPath = $this->projectDir.'/var/config/locale_configuration.json';
        if (is_file($configPath)) {
            $raw = file_get_contents($configPath);
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $active = is_array($decoded['active_locales'] ?? null) ? $decoded['active_locales'] : [];
                    $default = is_string($decoded['default_locale'] ?? null) ? $decoded['default_locale'] : '';

                    return [
                        'active_locales' => array_values(array_filter($active, 'is_string')),
                        'default_locale' => $default,
                    ];
                }
            }
        }

        $runtime = $this->localeConfigurationService->getConfiguration();

        return [
            'active_locales' => $runtime['activeLocales'],
            'default_locale' => $runtime['defaultLocale'],
        ];
    }

    /**
     * @brief Ensure combined data and file payload does not exceed configured export limit.
     *
     * @param array<string, string> $entryContents Current data entry bytes.
     * @param list<string> $filePaths Relative public file paths to include.
     * @param string $publicDir Absolute public directory path.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    /**
     * @param list<string> $employmentFilePaths Paths relative to project root under var/employment_documents/.
     */
    private function assertExportSizeWithinLimit(
        array $entryContents,
        array $filePaths,
        string $publicDir,
        array $employmentFilePaths = [],
    ): void {
        $totalBytes = 0;
        foreach ($entryContents as $bytes) {
            $totalBytes += strlen($bytes);
        }

        foreach ($filePaths as $relativePath) {
            $absolute = $publicDir.'/'.$relativePath;
            if (!is_file($absolute)) {
                continue;
            }

            $size = filesize($absolute);
            if ($size === false) {
                throw CustomizationBackupException::withReason('export_failed');
            }

            $totalBytes += $size;
        }

        foreach ($employmentFilePaths as $relativePath) {
            $absolute = $this->projectDir.'/'.$relativePath;
            if (!is_file($absolute)) {
                continue;
            }

            $size = filesize($absolute);
            if ($size === false) {
                throw CustomizationBackupException::withReason('export_failed');
            }

            $totalBytes += $size;
        }

        if ($totalBytes > $this->maxExportBytes) {
            throw CustomizationBackupException::withReason('export_too_large');
        }
    }

    /**
     * @brief Encode value as pretty-printed JSON bytes.
     *
     * @param mixed $payload Serializable structure.
     * @return string UTF-8 JSON bytes.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function encodeJson(mixed $payload): string
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $encoded;
    }

    /**
     * @brief Write ZIP entries to a temporary archive and return its bytes.
     *
     * @param array<string, string> $entryContents Map of entry path to raw bytes.
     * @return string ZIP binary payload.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function createZipFromEntries(array $entryContents): string
    {
        $tempZip = tempnam(sys_get_temp_dir(), 'cv-custom-backup-');
        if ($tempZip === false) {
            throw CustomizationBackupException::withReason('export_failed');
        }

        $zipPath = $tempZip.'.zip';
        @unlink($tempZip);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw CustomizationBackupException::withReason('export_failed');
        }

        foreach ($entryContents as $path => $bytes) {
            if (!$zip->addFromString($path, $bytes)) {
                $zip->close();
                @unlink($zipPath);
                throw CustomizationBackupException::withReason('export_failed');
            }
        }

        $zip->close();

        $zipBytes = file_get_contents($zipPath);
        @unlink($zipPath);
        if ($zipBytes === false) {
            throw CustomizationBackupException::withReason('export_failed');
        }

        return $zipBytes;
    }
}
