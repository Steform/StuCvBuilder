<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Cv\CompanyCvAssetPaths;
use App\Cv\CvProfilePersistenceScope;
use App\Employment\CompanyCvContentMode;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Exception\Employment\CompanyCvProfileCloneException;
use App\Repository\CvProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use FilesystemIterator;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * @brief Create and destroy per-company CvProfile clones (custom mode).
 *
 * Ops note: PHP (Apache/CLI) must be able to write under public/images/cv and
 * public/documents/cv so company asset directories can be created and filled.
 */
class CompanyCvProfileCloneService
{
    /**
     * @brief Wire clone dependencies.
     *
     * @param EntityManagerInterface $entityManager ORM.
     * @param CvProfileRepository $cvProfileRepository Profile repository.
     * @param LoggerInterface $logger Application logger.
     * @param string $projectDir Project root directory.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Ensure a company has a CvProfile clone for custom mode (create from global when missing).
     *
     * @param TrackedCompany $company Tracked company.
     * @return CvProfile Company profile row.
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function ensureClone(TrackedCompany $company): CvProfile
    {
        $existing = $this->cvProfileRepository->findOneForCompany($company);
        if ($existing !== null) {
            return $existing;
        }

        return $this->createCloneFromGlobal($company);
    }

    /**
     * @brief Create a new company CvProfile by snapshotting the global profile and copying custom assets.
     *
     * @param TrackedCompany $company Tracked company (must not already have a clone).
     * @return CvProfile
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function createCloneFromGlobal(TrackedCompany $company): CvProfile
    {
        $existing = $this->cvProfileRepository->findOneForCompany($company);
        if ($existing !== null) {
            return $existing;
        }

        $global = $this->cvProfileRepository->findGlobal();
        $title = $global !== null ? $global->getTitle() : 'CV';
        $payload = [];
        if ($global !== null) {
            $decoded = json_decode($global->getContentJson(), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        try {
            $this->ensureCompanyAssetRootsWritable($company->getCode());
            // Copy custom assets first, then sanitize so company-scoped paths remain valid.
            $payload = $this->duplicateCustomAssetsInPayload($payload, $company->getCode());
            $payload = CvProfilePersistenceScope::sanitizeForPersistence($payload);
        } catch (Throwable $exception) {
            $this->deleteCompanyAssetDirectories($company->getCode());
            if ($exception instanceof CompanyCvProfileCloneException) {
                throw $exception;
            }

            throw new CompanyCvProfileCloneException(
                'Failed to clone company CV assets for '.$company->getCode().': '.$exception->getMessage(),
                $exception,
            );
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $profile = new CvProfile($title, is_string($json) ? $json : '{}');
        $profile->setTrackedCompany($company);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }

    /**
     * @brief Switch company to custom mode and ensure a clone exists.
     *
     * @param TrackedCompany $company Tracked company.
     * @return CvProfile
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function switchToCustom(TrackedCompany $company): CvProfile
    {
        $previousMode = $company->getCvContentMode();
        $company->setCvContentMode(CompanyCvContentMode::CUSTOM);

        try {
            $profile = $this->ensureClone($company);
            $this->entityManager->flush();

            return $profile;
        } catch (Throwable $exception) {
            $partial = $this->cvProfileRepository->findOneForCompany($company);
            if ($partial !== null) {
                $this->entityManager->remove($partial);
            }
            $company->setCvContentMode($previousMode);
            $this->entityManager->flush();
            $this->deleteCompanyAssetDirectories($company->getCode());

            if ($exception instanceof CompanyCvProfileCloneException) {
                throw $exception;
            }

            throw new CompanyCvProfileCloneException(
                'Failed to switch company '.$company->getCode().' to custom CV mode: '.$exception->getMessage(),
                $exception,
            );
        }
    }

    /**
     * @brief Switch company to synced mode and remove its clone and assets.
     *
     * @param TrackedCompany $company Tracked company.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function switchToSynced(TrackedCompany $company): void
    {
        $profile = $this->cvProfileRepository->findOneForCompany($company);
        if ($profile !== null) {
            $this->entityManager->remove($profile);
        }

        $company->setCvContentMode(CompanyCvContentMode::SYNCED);
        $this->entityManager->flush();
        $this->deleteCompanyAssetDirectories($company->getCode());
    }

    /**
     * @brief Best-effort delete of on-disk asset directories for a company code.
     *
     * @param string $companyCode Tracked company code.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function deleteCompanyAssetDirectories(string $companyCode): void
    {
        foreach (CompanyCvAssetPaths::absoluteCompanyAssetDirectories($this->projectDir, $companyCode) as $directory) {
            $this->removeDirectoryRecursive($directory);
        }
    }

    /**
     * @brief Decode company profile payload or empty array.
     *
     * @param CvProfile $profile Company or global profile.
     * @return array<string, mixed>
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function decodePayload(CvProfile $profile): array
    {
        $decoded = json_decode($profile->getContentJson(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @brief Persist sanitized payload onto a profile.
     *
     * @param CvProfile $profile Target profile.
     * @param array<string, mixed> $payload Decoded content.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function persistPayload(CvProfile $profile, array $payload): void
    {
        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);
        $json = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $profile->setContentJson(is_string($json) ? $json : '{}');
        $this->entityManager->flush();
    }

    /**
     * @brief Create company asset roots and ensure they are writable by the current PHP process.
     *
     * @param string $companyCode Tracked company code.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    private function ensureCompanyAssetRootsWritable(string $companyCode): void
    {
        foreach (CompanyCvAssetPaths::absoluteCompanyAssetDirectories($this->projectDir, $companyCode) as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                $message = sprintf('Cannot create company CV asset directory "%s".', $directory);
                $this->logger->error($message, ['companyCode' => $companyCode]);
                throw new CompanyCvProfileCloneException($message);
            }

            if (!is_writable($directory)) {
                $message = sprintf('Company CV asset directory is not writable: "%s".', $directory);
                $this->logger->error($message, ['companyCode' => $companyCode]);
                throw new CompanyCvProfileCloneException($message);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function duplicateCustomAssetsInPayload(array $payload, string $companyCode): array
    {
        return $this->walkAndRewritePaths($payload, $companyCode);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function walkAndRewritePaths(mixed $value, string $companyCode): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $child) {
                $out[$key] = $this->walkAndRewritePaths($child, $companyCode);
            }

            return $out;
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        $target = CompanyCvAssetPaths::rewriteCustomizablePathForCompany($value, $companyCode);
        if ($target === null || $target === $value) {
            return $value;
        }

        $this->copyPublicFileOrFail($value, $target, $companyCode);

        return $target;
    }

    /**
     * @brief Copy one public-relative file into the company tree or throw.
     *
     * @param string $sourceRelative Source path relative to public/.
     * @param string $targetRelative Target path relative to public/.
     * @param string $companyCode Company code for logging.
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    private function copyPublicFileOrFail(string $sourceRelative, string $targetRelative, string $companyCode): void
    {
        $public = rtrim($this->projectDir, '/').'/public';
        $source = $public.'/'.ltrim(str_replace('\\', '/', $sourceRelative), '/');
        $target = $public.'/'.ltrim(str_replace('\\', '/', $targetRelative), '/');

        if (!is_file($source)) {
            $message = sprintf('Company CV clone source asset missing: "%s".', $sourceRelative);
            $this->logger->error($message, ['companyCode' => $companyCode, 'source' => $sourceRelative, 'target' => $targetRelative]);
            throw new CompanyCvProfileCloneException($message);
        }

        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $message = sprintf('Cannot create directory for company CV asset "%s".', $targetRelative);
            $this->logger->error($message, ['companyCode' => $companyCode, 'source' => $sourceRelative, 'target' => $targetRelative]);
            throw new CompanyCvProfileCloneException($message);
        }

        if (!is_writable($dir)) {
            $message = sprintf('Cannot write company CV asset into "%s" (directory not writable).', $targetRelative);
            $this->logger->error($message, ['companyCode' => $companyCode, 'source' => $sourceRelative, 'target' => $targetRelative]);
            throw new CompanyCvProfileCloneException($message);
        }

        if (!@copy($source, $target) || !is_file($target)) {
            $message = sprintf('Failed to copy company CV asset from "%s" to "%s".', $sourceRelative, $targetRelative);
            $this->logger->error($message, ['companyCode' => $companyCode, 'source' => $sourceRelative, 'target' => $targetRelative]);
            throw new CompanyCvProfileCloneException($message);
        }
    }

    private function removeDirectoryRecursive(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
