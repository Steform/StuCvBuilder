<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Cv\CvProfilePersistenceScope;
use App\Entity\CvProfile;
use App\Entity\HomeCustomization;
use App\Entity\HomeCustomizationTranslation;
use App\Repository\CvProfileRepository;
use App\Service\Home\HomeCustomizationService;
use App\Service\Site\SiteSeoResolverService;
use App\Service\Home\HomeQuickTilePresetRegistry;
use App\Service\Home\HomeQuickTileService;
use App\Service\Locale\LocaleConfigurationService;
use App\Exception\Customization\CustomizationBackupException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ZipArchive;

/**
 * @brief Restore site customization from an encrypted backup blob.
 */
final class CustomizationBackupImportService
{
    /**
     * @param string $projectDir Symfony project root directory.
     * @param int $maxUploadBytes Maximum allowed uploaded backup size in bytes.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly HomeQuickTileService $homeQuickTileService,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CustomizationBackupCryptoService $cryptoService,
        private readonly CustomizationBackupManifestValidator $manifestValidator,
        private readonly CustomizationBackupRestoreFailureClassifier $restoreFailureClassifier,
        private readonly CustomizationEmploymentBackupService $employmentBackupService,
        private readonly int $maxUploadBytes,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Restore customization from an uploaded encrypted backup file.
     *
     * @param UploadedFile $upload Uploaded .cvbackup file.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function restoreFromUpload(UploadedFile $upload): void
    {
        if (!$this->cryptoService->isConfigured()) {
            throw CustomizationBackupException::withReason('key_missing');
        }

        if (!$upload->isValid()) {
            throw CustomizationBackupException::withReason('upload_invalid', [
                '%error_code%' => (string) $upload->getError(),
            ]);
        }

        $size = (int) $upload->getSize();
        if ($size <= 0) {
            throw CustomizationBackupException::withReason('file_empty');
        }

        if ($size > $this->maxUploadBytes) {
            throw CustomizationBackupException::withReason('file_too_large', [
                '%max_size%' => CustomizationBackupException::formatBytes($this->maxUploadBytes),
                '%actual_size%' => CustomizationBackupException::formatBytes($size),
            ]);
        }

        $encrypted = file_get_contents($upload->getPathname());
        if ($encrypted === false || $encrypted === '') {
            throw CustomizationBackupException::withReason('file_empty');
        }

        $this->restoreFromEncryptedBlob($encrypted);
    }

    /**
     * @brief Restore customization from encrypted backup bytes.
     *
     * @param string $encryptedBlob Encrypted backup payload.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function restoreFromEncryptedBlob(string $encryptedBlob): void
    {
        $zipBytes = $this->cryptoService->decrypt($encryptedBlob);
        $entryContents = $this->extractZipEntries($zipBytes);

        $manifestRaw = $entryContents[CustomizationBackupPaths::MANIFEST] ?? null;
        if ($manifestRaw === null) {
            throw CustomizationBackupException::withReason('manifest_missing');
        }

        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest)) {
            throw CustomizationBackupException::withReason('manifest_invalid_json');
        }

        $this->manifestValidator->validate($manifest, $entryContents);

        $homeData = $this->decodeRequiredJson($entryContents, CustomizationBackupPaths::DATA_HOME);
        $homeTranslations = $this->decodeRequiredJson($entryContents, CustomizationBackupPaths::DATA_HOME_TRANSLATIONS);
        $cvData = $this->decodeRequiredJson($entryContents, CustomizationBackupPaths::DATA_CV_PROFILE);
        $localeData = $this->decodeRequiredJson($entryContents, CustomizationBackupPaths::DATA_LOCALE);

        try {
            $this->entityManager->wrapInTransaction(function () use ($homeData, $homeTranslations, $cvData, $entryContents): void {
                $this->restoreHomeCustomizationInTransaction($homeData, $homeTranslations);
                $this->restoreCvProfileInTransaction($cvData);
                $this->employmentBackupService->restoreDatabaseFromArchiveEntries($entryContents);
            });
        } catch (\Throwable $exception) {
            if ($exception instanceof CustomizationBackupException) {
                throw $exception;
            }

            throw $this->restoreFailureClassifier->classify(
                $exception,
                $this->restoreFailureClassifier->inferStepFromFailure($exception)
            );
        }

        try {
            $this->applyLocaleConfiguration($localeData);
        } catch (\Throwable $exception) {
            if ($exception instanceof CustomizationBackupException) {
                throw $exception;
            }

            throw CustomizationBackupException::withReason('file_write_failed', [
                '%path%' => 'var/config/locale_configuration.json',
            ], $exception);
        }

        $this->copyFilesFromEntries($entryContents);
        $this->employmentBackupService->restoreStorageFilesFromArchiveEntries($entryContents);
    }

    /**
     * @brief Extract ZIP members into an in-memory map.
     *
     * @param string $zipBytes ZIP binary payload.
     * @return array<string, string> Map of entry path to raw bytes.
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function extractZipEntries(string $zipBytes): array
    {
        $tempZip = tempnam(sys_get_temp_dir(), 'cv-custom-restore-');
        if ($tempZip === false) {
            throw CustomizationBackupException::withReason('zip_temp_failed');
        }

        $zipPath = $tempZip.'.zip';
        @unlink($tempZip);
        if (file_put_contents($zipPath, $zipBytes) === false) {
            throw CustomizationBackupException::withReason('zip_write_failed');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);
            throw CustomizationBackupException::withReason('zip_unreadable');
        }

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat)) {
                continue;
            }

            $name = (string) ($stat['name'] ?? '');
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $content = $zip->getFromIndex($index);
            if ($content === false) {
                $zip->close();
                @unlink($zipPath);
                throw CustomizationBackupException::withReason('zip_entry_read_failed', [
                    '%path%' => $name,
                ]);
            }

            $entries[$name] = $content;
        }

        $zip->close();
        @unlink($zipPath);

        return $entries;
    }

    /**
     * @brief Decode a required JSON entry from the archive.
     *
     * @param array<string, string> $entryContents Extracted ZIP entries.
     * @param string $path Entry path inside archive.
     * @return array<string, mixed>|list<mixed>
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function decodeRequiredJson(array $entryContents, string $path): array
    {
        $raw = $entryContents[$path] ?? null;
        if (!is_string($raw)) {
            throw CustomizationBackupException::withReason('json_entry_missing', [
                '%path%' => $path,
            ]);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw CustomizationBackupException::withReason('json_entry_invalid', [
                '%path%' => $path,
            ]);
        }

        return $decoded;
    }

    /**
     * @brief Restore home customization inside the database transaction with step-scoped errors.
     *
     * @param array<string, mixed> $homeData Scalar field map.
     * @param array<int, mixed> $translationsData Translation rows.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function restoreHomeCustomizationInTransaction(array $homeData, array $translationsData): void
    {
        $home = $this->homeCustomizationService->getOrCreateSingleton();

        try {
            $this->applyHomeScalarFields($home, $homeData);
        } catch (\Throwable $exception) {
            throw $this->restoreFailureClassifier->classify(
                $exception,
                CustomizationBackupRestoreFailureClassifier::STEP_HOME_SCALARS
            );
        }

        try {
            $this->applyHomeTranslations($home, $translationsData);
        } catch (\Throwable $exception) {
            throw $this->restoreFailureClassifier->classify(
                $exception,
                CustomizationBackupRestoreFailureClassifier::STEP_HOME_TRANSLATIONS
            );
        }

        try {
            $quickTilesPayload = $homeData['quickTiles'] ?? [];
            if (!is_array($quickTilesPayload)) {
                $quickTilesPayload = [];
            }

            $this->homeQuickTileService->replaceAllFromBackup($home, $quickTilesPayload);
        } catch (\Throwable $exception) {
            throw $this->restoreFailureClassifier->classify(
                $exception,
                CustomizationBackupRestoreFailureClassifier::STEP_HOME_QUICK_TILES
            );
        }

        try {
            $this->entityManager->persist($home);
        } catch (\Throwable $exception) {
            throw $this->restoreFailureClassifier->classify(
                $exception,
                CustomizationBackupRestoreFailureClassifier::STEP_HOME_SCALARS
            );
        }
    }

    /**
     * @brief Apply scalar fields on the home customization entity.
     *
     * @param HomeCustomization $home Target entity row.
     * @param array<string, mixed> $homeData Scalar field map from backup.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function applyHomeScalarFields(HomeCustomization $home, array $homeData): void
    {
        $home->setSignatureImageRelativePath($this->nullableString($homeData['signatureImageRelativePath'] ?? null));
        $home->setBackgroundImageRelativePath($this->nullableString($homeData['backgroundImageRelativePath'] ?? null));
        $home->setIntroTitleCssSanitized($this->nullableString($homeData['introTitleCssSanitized'] ?? null));
        $home->setWebcvButtonCssSanitized($this->nullableString($homeData['webcvButtonCssSanitized'] ?? null));
        $home->setWebcvButtonCssHoverSanitized($this->nullableString($homeData['webcvButtonCssHoverSanitized'] ?? null));
        $home->setBackgroundCssSanitized($this->nullableString($homeData['backgroundCssSanitized'] ?? null));
        $home->setSignatureCssSanitized($this->nullableString($homeData['signatureCssSanitized'] ?? null));
        $quickTileStyle = $this->resolveQuickTileStyleFromBackup($homeData);
        $home->setQuickTileStyle($quickTileStyle);
        $quickTileCss = $this->nullableString($homeData['quickTileCssSanitized'] ?? null);
        if ($quickTileCss === null && $quickTileStyle === HomeQuickTilePresetRegistry::STYLE_CUSTOM) {
            $quickTileCss = $this->nullableString($homeData['dashboardTileCssSanitized'] ?? null);
        }
        $home->setQuickTileCssSanitized($quickTileCss);
        $home->setDashboardTileIconRelativePath($this->nullableString($homeData['dashboardTileIconRelativePath'] ?? null));
        $home->setSiteFaviconRelativePath($this->nullableString($homeData['siteFaviconRelativePath'] ?? null));
        $home->setOpenGraphImageRelativePath($this->nullableString($homeData['openGraphImageRelativePath'] ?? null));
        if (isset($homeData['cvAntibotThreshold']) && is_numeric($homeData['cvAntibotThreshold'])) {
            $home->setCvAntibotThreshold((int) $homeData['cvAntibotThreshold']);
        }
        if (array_key_exists('maintenanceModeEnabled', $homeData)) {
            $home->setMaintenanceModeEnabled((bool) $homeData['maintenanceModeEnabled']);
        }
        $home->setSiteColorsJson($this->nullableString($homeData['siteColorsJson'] ?? null));
        $home->setMailTemplatesJson($this->nullableString($homeData['mailTemplatesJson'] ?? null));
    }

    /**
     * @brief Replace home customization translations from backup rows.
     *
     * Existing rows are removed through the entity manager (orphanRemoval-safe) and flushed
     * before new rows are attached so uniq_home_customization_locale is not violated.
     *
     * @param HomeCustomization $home Target entity row.
     * @param array<int, mixed> $translationsData Translation rows from backup.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function applyHomeTranslations(HomeCustomization $home, array $translationsData): void
    {
        $existingTranslations = $home->getTranslations()->toArray();
        foreach ($existingTranslations as $existing) {
            $home->removeTranslation($existing);
            $this->entityManager->remove($existing);
        }

        // Flush only pending translation deletes before inserts (tiles step must not flush earlier).
        if ($existingTranslations !== []) {
            $this->entityManager->flush();
        }

        foreach ($this->deduplicateHomeTranslationRows($translationsData) as $row) {
            $locale = isset($row['locale']) && is_string($row['locale']) ? trim($row['locale']) : '';
            $introText = isset($row['introText']) && is_string($row['introText'])
                ? HomeCustomizationService::normalizeStoredIntroText($row['introText'])
                : '';
            $metaDescription = isset($row['metaDescription']) && is_string($row['metaDescription'])
                ? SiteSeoResolverService::normalizeMetaDescription($row['metaDescription'])
                : '';
            if ($locale === '') {
                continue;
            }

            $translation = new HomeCustomizationTranslation();
            $translation->setLocale($locale);
            $translation->setIntroText($introText);
            $translation->setMetaDescription($metaDescription);
            $home->addTranslation($translation);
        }
    }

    /**
     * @brief Keep one backup row per locale (last row wins) to satisfy uniq_home_customization_locale.
     *
     * @param array<int, mixed> $translationsData Raw translation rows from backup JSON.
     * @return list<array{locale: string, introText: string, metaDescription: string}>
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function deduplicateHomeTranslationRows(array $translationsData): array
    {
        /** @var array<string, array{locale: string, introText: string, metaDescription: string}> $byLocale */
        $byLocale = [];

        foreach ($translationsData as $row) {
            if (!is_array($row)) {
                continue;
            }

            $locale = isset($row['locale']) && is_string($row['locale']) ? trim($row['locale']) : '';
            if ($locale === '') {
                continue;
            }

            $introText = isset($row['introText']) && is_string($row['introText']) ? $row['introText'] : '';
            $metaDescription = isset($row['metaDescription']) && is_string($row['metaDescription']) ? $row['metaDescription'] : '';
            $byLocale[strtolower($locale)] = [
                'locale' => $locale,
                'introText' => $introText,
                'metaDescription' => $metaDescription,
            ];
        }

        return array_values($byLocale);
    }

    /**
     * @brief Restore CV profile inside the database transaction with step-scoped errors.
     *
     * @param array<string, mixed> $cvData CV profile payload from backup.
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    private function restoreCvProfileInTransaction(array $cvData): void
    {
        try {
            $this->applyCvProfile($cvData);
        } catch (\Throwable $exception) {
            throw $this->restoreFailureClassifier->classify(
                $exception,
                CustomizationBackupRestoreFailureClassifier::STEP_CV_PROFILE
            );
        }
    }

    /**
     * @brief Apply CV profile title and sanitized JSON content to latest profile row.
     *
     * @param array<string, mixed> $cvData CV profile payload.
     * @return void
     * @date 2026-05-27
     * @author Stephane H.
     */
    private function applyCvProfile(array $cvData): void
    {
        $title = isset($cvData['title']) && is_string($cvData['title']) ? trim($cvData['title']) : 'CV';
        if ($title === '') {
            $title = 'CV';
        }

        $content = $cvData['contentJson'] ?? [];
        if (!is_array($content)) {
            $content = [];
        }

        $content = CvProfilePersistenceScope::sanitizeForPersistence($content);

        $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if ($profile === null) {
            $profile = new CvProfile($title, $contentJson);
            $this->entityManager->persist($profile);

            return;
        }

        $profile->setTitle($title);
        $profile->setContentJson($contentJson);
        $this->entityManager->persist($profile);
    }

    /**
     * @brief Persist locale configuration after database transaction commit.
     *
     * @param array<string, mixed> $localeData Locale JSON payload.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function applyLocaleConfiguration(array $localeData): void
    {
        $active = is_array($localeData['active_locales'] ?? null) ? $localeData['active_locales'] : [];
        $activeLocales = array_values(array_filter($active, 'is_string'));
        $defaultLocale = is_string($localeData['default_locale'] ?? null) ? $localeData['default_locale'] : '';

        if ($activeLocales === [] || $defaultLocale === '') {
            throw CustomizationBackupException::withReason('locale_data_invalid');
        }

        $this->localeConfigurationService->saveConfiguration($activeLocales, $defaultLocale);
    }

    /**
     * @brief Copy archived public files after successful database restore.
     *
     * @param array<string, string> $entryContents Extracted ZIP entries.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function copyFilesFromEntries(array $entryContents): void
    {
        $publicDir = $this->projectDir.'/public';
        $prefix = CustomizationBackupPaths::FILES_PREFIX;
        $prefixLength = strlen($prefix);

        foreach ($entryContents as $entryPath => $bytes) {
            if (!str_starts_with($entryPath, $prefix)) {
                continue;
            }

            $relative = substr($entryPath, $prefixLength);
            if ($relative === '' || str_contains($relative, '..')) {
                throw CustomizationBackupException::withReason('path_traversal_blocked', [
                    '%path%' => $entryPath,
                ]);
            }

            $target = $publicDir.'/'.$relative;
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw CustomizationBackupException::withReason('directory_create_failed', [
                    '%path%' => $relative,
                ]);
            }

            if (file_put_contents($target, $bytes) === false) {
                throw CustomizationBackupException::withReason('file_write_failed', [
                    '%path%' => $relative,
                ]);
            }
        }
    }

    /**
     * @brief Resolve quick tile style from backup payload with legacy field fallback.
     *
     * @param array<string, mixed> $homeData Scalar home customization map.
     * @return string Valid style key defaulting to style_1.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function resolveQuickTileStyleFromBackup(array $homeData): string
    {
        $style = isset($homeData['quickTileStyle']) && is_string($homeData['quickTileStyle'])
            ? trim($homeData['quickTileStyle'])
            : '';

        if ($style !== '' && in_array($style, HomeQuickTilePresetRegistry::ALLOWED_STYLES, true)) {
            return $style;
        }

        $legacyDashboard = $this->nullableString($homeData['dashboardTileCssSanitized'] ?? null);
        if ($legacyDashboard !== null) {
            return HomeQuickTilePresetRegistry::STYLE_CUSTOM;
        }

        return 'style_1';
    }

    /**
     * @brief Coerce mixed value to nullable trimmed string.
     *
     * @param mixed $value Raw JSON scalar.
     * @return string|null
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
