<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Service\Customization\CustomizationAssetScope;
use App\Service\Cv\CertificationContract;
use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Cv\ExperienceContract;
use App\Service\Cv\FlagshipProjectsContract;
use App\Service\Home\HomeCustomizationService;
use PHPUnit\Framework\TestCase;

final class CustomizationAssetScopeTest extends TestCase
{
    /**
     * @brief Upload directory constants must stay aligned with feature services.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testPurgeableRootsMatchFeatureConstants(): void
    {
        $roots = CustomizationAssetScope::getPurgeableDirectoryRoots();

        self::assertContains(HomeCustomizationService::UPLOAD_SUBDIRECTORY, $roots);
        self::assertContains(HomeCustomizationService::FAVICON_CUSTOM_UPLOAD_ROOT, $roots);
        self::assertContains(CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT, $roots);
        self::assertContains(rtrim(ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX, '/'), $roots);
        self::assertContains(rtrim(FlagshipProjectsContract::PREVIEW_IMAGE_PATH_PREFIX, '/'), $roots);
        self::assertContains(rtrim(CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX, '/'), $roots);
    }

    /**
     * @brief System tile icons and fallbacks must be protected from purge and export.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testProtectedPathsIncludeSystemAssets(): void
    {
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath('images/home/dashboard.svg'));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath('images/home/plus.svg'));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath(HomeCustomizationService::DEFAULT_SIGNATURE_PATH));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath(HomeCustomizationService::DEFAULT_BACKGROUND_PATH));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath(HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath(CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH));
        self::assertTrue(CustomizationAssetScope::isPurgeableRelativePath('images/home/custom/upload.webp'));
        self::assertTrue(CustomizationAssetScope::isPurgeableRelativePath('favicon/custom/site-favicon-abc.svg'));
        self::assertFalse(CustomizationAssetScope::isProtectedRelativePath('images/home/custom/upload.webp'));
        self::assertTrue(CustomizationAssetScope::isPurgeableRelativePath('images/cv/about/custom/photo.webp'));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath('images/cv/textures/texture1.webp'));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath('images/cv/textures/texture6.webp'));
        self::assertTrue(CustomizationAssetScope::isProtectedRelativePath(FlagshipProjectsContract::DEFAULT_PROJECT_PREVIEW_IMAGE_PATH));
        self::assertTrue(CustomizationAssetScope::isPurgeableRelativePath('images/cv/projects/custom/project-test.webp'));
        self::assertTrue(CustomizationAssetScope::isPurgeableRelativePath('documents/cv/certification/custom/certification-proof-test.pdf'));
    }

    /**
     * @brief Export filter must drop protected fallbacks but keep custom uploads.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testFilterExportablePathsExcludesProtected(): void
    {
        $filtered = CustomizationAssetScope::filterExportablePaths([
            'images/home/custom/upload.webp',
            'images/home/custom/signature.webp',
            'images/home/dashboard.svg',
        ]);

        self::assertSame(
            ['images/home/custom/upload.webp', 'images/home/custom/signature.webp'],
            $filtered
        );
    }
}
