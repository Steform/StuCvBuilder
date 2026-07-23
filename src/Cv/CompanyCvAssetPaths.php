<?php

declare(strict_types=1);

namespace App\Cv;

use App\Service\Customization\CustomizationAssetScope;

/**
 * @brief Relative public paths for per-company CV clone assets (under existing custom roots).
 */
final class CompanyCvAssetPaths
{
    public const COMPANY_SEGMENT = 'company';

    /**
     * @brief Rewrite a purgeable global custom path into a company-scoped path under the same root.
     *
     * Example: images/cv/experience/custom/a.webp
     *       -> images/cv/experience/custom/company/{code}/a.webp
     *
     * @param string $relativePath Path relative to public/.
     * @param string $companyCode Tracked company code.
     * @return string|null Rewritten path, or null when the source must stay shared (system asset).
     * @date 2026-07-23
     * @author Stephane H.
     */
    public static function rewriteCustomizablePathForCompany(string $relativePath, string $companyCode): ?string
    {
        $normalized = self::normalize($relativePath);
        $code = trim($companyCode);
        if ($normalized === null || $code === '') {
            return null;
        }

        if (self::isCompanyScopedPath($normalized)) {
            return null;
        }

        if (!CustomizationAssetScope::isPurgeableRelativePath($normalized)) {
            return null;
        }

        foreach (CustomizationAssetScope::getPurgeableDirectoryRoots() as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            if ($normalized === $root || str_starts_with($normalized, $root.'/')) {
                $suffix = $normalized === $root ? '' : substr($normalized, strlen($root) + 1);
                $target = $root.'/'.self::COMPANY_SEGMENT.'/'.$code;
                if ($suffix !== '') {
                    $target .= '/'.$suffix;
                }

                return $target;
            }
        }

        return null;
    }

    /**
     * @brief Whether a path lies under any …/company/{code}/ tree.
     *
     * @param string $path Path relative to public/.
     * @param string|null $companyCode Optional specific company code.
     * @return bool
     * @date 2026-07-23
     * @author Stephane H.
     */
    public static function isCompanyScopedPath(string $path, ?string $companyCode = null): bool
    {
        $normalized = self::normalize($path);
        if ($normalized === null) {
            return false;
        }

        $needle = '/'.self::COMPANY_SEGMENT.'/';
        if ($companyCode !== null && trim($companyCode) !== '') {
            $needle .= trim($companyCode);
        }

        return str_contains($normalized, $needle);
    }

    /**
     * @brief List absolute filesystem directories that store assets for one company code.
     *
     * @param string $projectDir Project root.
     * @param string $companyCode Tracked company code.
     * @return list<string> Absolute directory paths that may exist.
     * @date 2026-07-23
     * @author Stephane H.
     */
    public static function absoluteCompanyAssetDirectories(string $projectDir, string $companyCode): array
    {
        $code = trim($companyCode);
        if ($code === '') {
            return [];
        }

        $public = rtrim($projectDir, '/').'/public';
        $dirs = [];
        foreach (CustomizationAssetScope::getPurgeableDirectoryRoots() as $root) {
            $dirs[] = $public.'/'.rtrim(str_replace('\\', '/', $root), '/').'/'.self::COMPANY_SEGMENT.'/'.$code;
        }

        return $dirs;
    }

    /**
     * @param string $path Raw relative path.
     * @return string|null Normalized path or null when empty/unsafe.
     */
    private static function normalize(string $path): ?string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }

        return $normalized;
    }
}
