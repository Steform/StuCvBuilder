<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Entity\CvProfile;
use App\Entity\HomeCustomization;
use App\Service\Home\HomeCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Exception\Customization\CustomizationBackupException;

/**
 * @brief Wipe customization backup scope and insert minimal home placeholder row.
 */
final class CustomizationResetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly CustomizationPreResetBackupWriter $preResetBackupWriter,
        private readonly CustomizationBackupFileCollector $fileCollector,
        private readonly CustomizationBackupCryptoService $cryptoService,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Run pre-reset backup then wipe DB, locale file, and public image trees.
     *
     * @return string Basename of the pre-reset snapshot written on disk.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function reset(): string
    {
        if (!$this->cryptoService->isConfigured()) {
            throw CustomizationBackupException::withReason('key_missing');
        }

        $snapshotBasename = $this->preResetBackupWriter->writePreResetSnapshot();

        $this->entityManager->wrapInTransaction(function (): void {
            $this->entityManager->createQuery('DELETE FROM '.CvProfile::class.' p')->execute();
            $this->entityManager->createQuery('DELETE FROM '.HomeCustomization::class.' h')->execute();
            $this->entityManager->flush();
            $this->homeCustomizationService->createPlaceholderSingleton();
        });

        try {
            $this->deleteLocaleConfigurationFile();
            $this->fileCollector->purgeCustomizableImageDirectories();
        } catch (\Throwable $exception) {
            throw CustomizationBackupException::withReason('reset_partial', [], $exception);
        }

        return $snapshotBasename;
    }

    /**
     * @brief Remove persisted locale configuration JSON when present.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function deleteLocaleConfigurationFile(): void
    {
        $path = rtrim($this->projectDir, '/').'/var/config/locale_configuration.json';
        if (is_file($path)) {
            unlink($path);
        }
    }
}
