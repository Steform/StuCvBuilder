<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Entity\HomeCustomization;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use App\Exception\Customization\CustomizationBackupException;

/**
 * @brief Collect public-relative file paths referenced by customization payloads and customizable upload trees.
 */
final class CustomizationBackupFileCollector
{
    private const CV_IMAGE_PREFIX = 'images/cv/';

    private const CV_DOCUMENT_PREFIX = 'documents/cv/';

    private const HOME_IMAGE_PREFIX = 'images/home/';

    private const FAVICON_CUSTOM_PREFIX = 'favicon/custom/';

    /**
     * @param string $projectDir Symfony project root directory.
     */
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @brief Collect relative public paths from home customization entity fields.
     *
     * @param HomeCustomization $home Home customization singleton.
     * @return list<string> Unique relative paths under public/.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function collectFromHome(HomeCustomization $home): array
    {
        $paths = [];
        $this->maybeAddPath($home->getSignatureImageRelativePath(), $paths);
        $this->maybeAddPath($home->getBackgroundImageRelativePath(), $paths);
        $this->maybeAddPath($home->getDashboardTileIconRelativePath(), $paths);
        $this->maybeAddPath($home->getSiteFaviconRelativePath(), $paths);

        return array_keys($paths);
    }

    /**
     * @brief Scan CV content JSON recursively for referenced image paths.
     *
     * @param array<string, mixed>|string $contentJson Decoded array or JSON string.
     * @return list<string> Unique relative paths under public/.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function collectFromCvContent(array|string $contentJson): array
    {
        if (is_string($contentJson)) {
            $decoded = json_decode($contentJson, true);
            if (!is_array($decoded)) {
                return [];
            }
            $contentJson = $decoded;
        }

        $paths = [];
        $this->scanValue($contentJson, $paths);

        return array_keys($paths);
    }

    /**
     * @brief Collect all files under customizable upload directory roots only.
     *
     * @param void No input parameter.
     * @return list<string> Unique relative paths under public/.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function collectCustomizableImageTrees(): array
    {
        $paths = [];
        foreach (CustomizationAssetScope::getPurgeableDirectoryRoots() as $root) {
            foreach ($this->collectDirectoryTree($root) as $path) {
                $paths[$path] = true;
            }
        }

        return array_keys($paths);
    }

    /**
     * @brief Recursively list regular files under a whitelisted customizable directory under public/.
     *
     * @param string $relativeRoot Path relative to public/ (e.g. images/home/custom).
     * @return list<string> Unique relative paths under public/.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function collectDirectoryTree(string $relativeRoot): array
    {
        $relativeRoot = str_replace('\\', '/', trim($relativeRoot, '/'));
        if (!CustomizationAssetScope::isPurgeableDirectoryRoot($relativeRoot) || str_contains($relativeRoot, '..')) {
            return [];
        }

        $publicDir = $this->projectDir.'/public';
        $publicReal = realpath($publicDir);
        if ($publicReal === false) {
            return [];
        }

        $absoluteRoot = $publicDir.'/'.$relativeRoot;
        if (!is_dir($absoluteRoot)) {
            return [];
        }

        $rootReal = realpath($absoluteRoot);
        if ($rootReal === false || !$this->isPathInsideDirectory($rootReal, $publicReal)) {
            return [];
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $rootReal,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            )
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $fileReal = realpath($fileInfo->getPathname());
            if ($fileReal === false || !$this->isPathInsideDirectory($fileReal, $publicReal)) {
                continue;
            }

            $relative = substr($fileReal, strlen($publicReal) + 1);
            $relative = str_replace('\\', '/', $relative);
            if ($relative !== '' && !str_contains($relative, '..')) {
                $paths[$relative] = true;
            }
        }

        return array_keys($paths);
    }

    /**
     * @brief Merge path lists and keep unique normalized entries.
     *
     * @param list<string> ...$lists Path lists to merge.
     * @return list<string>
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function mergePaths(array ...$lists): array
    {
        $merged = [];
        foreach ($lists as $list) {
            foreach ($list as $path) {
                $this->maybeAddPath(is_string($path) ? $path : null, $merged);
            }
        }

        return array_keys($merged);
    }

    /**
     * @brief Merge path lists and exclude protected system assets from backup export.
     *
     * @param list<string> ...$lists Path lists to merge.
     * @return list<string> Exportable relative paths under public/.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function mergeExportablePaths(array ...$lists): array
    {
        return CustomizationAssetScope::filterExportablePaths($this->mergePaths(...$lists));
    }

    /**
     * @brief Remove all files under customizable upload directories and ensure empty directories exist.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function purgeCustomizableImageDirectories(): void
    {
        $publicDir = $this->projectDir.'/public';

        foreach (CustomizationAssetScope::getPurgeableDirectoryRoots() as $relativeRoot) {
            $absoluteRoot = $publicDir.'/'.$relativeRoot;
            if (is_dir($absoluteRoot)) {
                $this->deleteFilesInDirectory($absoluteRoot);
            }

            if (!is_dir($absoluteRoot) && !mkdir($absoluteRoot, 0775, true) && !is_dir($absoluteRoot)) {
                throw CustomizationBackupException::withReason('reset_failed');
            }
        }
    }

    /**
     * @brief Recursively inspect JSON values for file path strings.
     *
     * @param mixed $value Current JSON node.
     * @param array<string, true> $paths Accumulator keyed by path.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function scanValue(mixed $value, array &$paths): void
    {
        if (is_string($value)) {
            $this->maybeAddPath($value, $paths);

            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->scanValue($item, $paths);
        }
    }

    /**
     * @brief Normalize and register a candidate relative public path.
     *
     * @param string|null $candidate Raw path from entity or JSON.
     * @param array<string, true> $paths Accumulator keyed by path.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function maybeAddPath(?string $candidate, array &$paths): void
    {
        if (!is_string($candidate) || trim($candidate) === '') {
            return;
        }

        $normalized = $this->normalizeRelativePublicPath($candidate);
        if ($normalized === null) {
            return;
        }

        $paths[$normalized] = true;
    }

    /**
     * @brief Normalize a path to a safe relative segment under public/.
     *
     * @param string $path Candidate path.
     * @return string|null Normalized path or null when rejected.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function normalizeRelativePublicPath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (
            str_starts_with($path, self::CV_IMAGE_PREFIX)
            || str_starts_with($path, self::CV_DOCUMENT_PREFIX)
            || str_starts_with($path, self::HOME_IMAGE_PREFIX)
            || str_starts_with($path, self::FAVICON_CUSTOM_PREFIX)
        ) {
            return $path;
        }

        if (str_contains($path, self::CV_DOCUMENT_PREFIX)) {
            $offset = strpos($path, self::CV_DOCUMENT_PREFIX);
            if ($offset === false) {
                return null;
            }

            return substr($path, $offset);
        }

        if (str_contains($path, self::CV_IMAGE_PREFIX)) {
            $offset = strpos($path, self::CV_IMAGE_PREFIX);
            if ($offset === false) {
                return null;
            }

            return substr($path, $offset);
        }

        if (str_contains($path, self::HOME_IMAGE_PREFIX)) {
            $offset = strpos($path, self::HOME_IMAGE_PREFIX);
            if ($offset === false) {
                return null;
            }

            return substr($path, $offset);
        }

        return null;
    }

    /**
     * @brief Check whether a resolved path stays inside the public directory.
     *
     * @param string $candidate Resolved absolute path.
     * @param string $directoryResolved Resolved public directory path.
     * @return bool
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function isPathInsideDirectory(string $candidate, string $directoryResolved): bool
    {
        if ($candidate === $directoryResolved) {
            return true;
        }

        $prefix = rtrim($directoryResolved, '/\\').'/';

        return str_starts_with($candidate, $prefix);
    }

    /**
     * @brief Delete regular files recursively under one directory without removing the directory itself.
     *
     * @param string $absoluteRoot Absolute directory path.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function deleteFilesInDirectory(string $absoluteRoot): void
    {
        if (!is_dir($absoluteRoot)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $absoluteRoot,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                unlink($fileInfo->getPathname());
            } elseif ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            }
        }
    }
}
