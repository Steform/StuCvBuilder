<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Cv\SkillsTreeContract;
use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\EducationContract;
use App\Service\Cv\ExperienceContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Cv\InterestsContract;
use App\Service\Home\HomeCustomizationService;

/**
 * @brief Canonical scope for customizable public assets (purge, backup, export).
 */
final class CustomizationAssetScope
{
    public const FILE_SCOPE_CUSTOMIZABLE_ONLY = 'customizable_only';

    /** @var string Home landing upload directory relative to public/. */
    public const HOME_CUSTOM_UPLOAD_ROOT = 'images/home/custom';

    /** @var string CV About profile photo upload directory relative to public/. */
    public const CV_ABOUT_CUSTOM_UPLOAD_ROOT = CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT;

    /** @var string Site favicon upload directory relative to public/. */
    public const FAVICON_CUSTOM_UPLOAD_ROOT = 'favicon/custom';

    /**
     * @var list<string> Explicit system asset paths that must never be purged or exported.
     */
    private const PROTECTED_RELATIVE_PATHS = [
        'images/home/dashboard.svg',
        'images/home/plus.svg',
        HomeCustomizationService::DEFAULT_SIGNATURE_PATH,
        HomeCustomizationService::DEFAULT_BACKGROUND_PATH,
        HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH,
        CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH,
        'images/cv/textures/texture1.webp',
        'images/cv/textures/texture2.webp',
        'images/cv/textures/texture3.webp',
        'images/cv/textures/texture4.webp',
        'images/cv/textures/texture5.webp',
        'images/cv/textures/texture6.webp',
        FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH,
    ];

    /**
     * @brief Return directory roots whose files may be purged on customization reset.
     *
     * @param void No input parameter.
     * @return list<string> Paths relative to public/ without trailing slash.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public static function getPurgeableDirectoryRoots(): array
    {
        return [
            self::HOME_CUSTOM_UPLOAD_ROOT,
            self::FAVICON_CUSTOM_UPLOAD_ROOT,
            self::CV_ABOUT_CUSTOM_UPLOAD_ROOT,
            rtrim(ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX, '/'),
            rtrim(EducationContract::EDUCATION_LOGO_PATH_PREFIX, '/'),
            rtrim(CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX, '/'),
            rtrim(SkillsTreeContract::SKILL_ICON_PATH_PREFIX, '/'),
            rtrim(InterestsContract::INTEREST_ICON_PATH_PREFIX, '/'),
            rtrim(FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX, '/'),
        ];
    }

    /**
     * @brief Return explicit non-customizable asset paths referenced by Twig or PHP fallbacks.
     *
     * @param void No input parameter.
     * @return list<string> Paths relative to public/.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public static function getProtectedRelativePaths(): array
    {
        return self::PROTECTED_RELATIVE_PATHS;
    }

    /**
     * @brief Check whether a relative public path lies under a customizable upload root.
     *
     * @param string $path Candidate path relative to public/.
     * @return bool True when the path is under a purgeable custom directory.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public static function isPurgeableRelativePath(string $path): bool
    {
        $normalized = self::normalizeRelativePath($path);
        if ($normalized === null) {
            return false;
        }

        foreach (self::getPurgeableDirectoryRoots() as $root) {
            if ($normalized === $root || str_starts_with($normalized, $root.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Check whether a relative public path must be preserved on reset and excluded from backup files.
     *
     * @param string $path Candidate path relative to public/.
     * @return bool True when the path is protected.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public static function isProtectedRelativePath(string $path): bool
    {
        $normalized = self::normalizeRelativePath($path);
        if ($normalized === null) {
            return true;
        }

        if (in_array($normalized, self::PROTECTED_RELATIVE_PATHS, true)) {
            return true;
        }

        return !self::isPurgeableRelativePath($normalized);
    }

    /**
     * @brief Check whether a directory root is allowed for tree collection or purge.
     *
     * @param string $relativeRoot Path relative to public/.
     * @return bool True when the root is a purgeable customizable directory.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public static function isPurgeableDirectoryRoot(string $relativeRoot): bool
    {
        $normalized = self::normalizeRelativePath($relativeRoot);
        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::getPurgeableDirectoryRoots(), true);
    }

    /**
     * @brief Drop protected paths from a merged export path list.
     *
     * @param list<string> $paths Candidate relative paths under public/.
     * @return list<string> Paths safe to include in a customization backup archive.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public static function filterExportablePaths(array $paths): array
    {
        $exportable = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            if (self::isProtectedRelativePath($path)) {
                continue;
            }

            $exportable[] = $path;
        }

        return array_values(array_unique($exportable));
    }

    /**
     * @brief Normalize a candidate path relative to the public web root.
     *
     * @param string $path Raw relative path.
     * @return string|null Normalized path or null when invalid.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private static function normalizeRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }
}
