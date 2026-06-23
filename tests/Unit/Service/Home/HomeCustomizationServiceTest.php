<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Home;

use App\Entity\HomeCustomization;
use App\Repository\HomeCustomizationRepository;
use App\Service\Home\HomeCustomizationService;
use App\Service\Home\HomeQuickTilePresetRegistry;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Security\CssSanitizerService;
use App\Service\Util\ImageReencoder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @brief Unit tests for {@see HomeCustomizationService} stylesheet and tile icon rules.
 * @date 2026-05-17
 * @author Stephane H.
 */
final class HomeCustomizationServiceTest extends TestCase
{
    private HomeCustomizationService $service;

    protected function setUp(): void
    {
        $this->service = new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(HomeCustomizationRepository::class),
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', sys_get_temp_dir()),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            sys_get_temp_dir(),
        );
    }

    /**
     * @brief Preset quick tile style must emit unified selector block.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testBuildStylesheetCssIncludesPresetQuickTileBlock(): void
    {
        $home = new HomeCustomization();
        $home->setQuickTileStyle('style_5');

        $css = $this->service->buildStylesheetCss($home);

        self::assertStringContainsString('.home-custom-quick-tile.button-icon', $css);
        self::assertStringContainsString('backdrop-filter', $css);
    }

    /**
     * @brief Custom quick tile CSS must be used when style is custom.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testBuildStylesheetCssUsesCustomQuickTileDeclarations(): void
    {
        $home = new HomeCustomization();
        $home->setQuickTileStyle(HomeQuickTilePresetRegistry::STYLE_CUSTOM);
        $home->setQuickTileCssSanitized('border-radius: 2rem; color: #f0f;');

        $css = $this->service->buildStylesheetCss($home);

        self::assertStringContainsString('border-radius: 2rem', $css);
        self::assertStringContainsString('.home-custom-quick-tile.button-icon', $css);
    }

    /**
     * @brief Background and signature CSS blocks must map to landing selectors.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testBuildStylesheetCssIncludesBackgroundAndSignatureBlocks(): void
    {
        $home = new HomeCustomization();
        $home->setBackgroundCssSanitized('min-height: 100vh;');
        $home->setSignatureCssSanitized('max-width: 12rem;');

        $css = $this->service->buildStylesheetCss($home);

        self::assertStringContainsString('.home-landing.home-custom-bg', $css);
        self::assertStringContainsString('min-height: 100vh', $css);
        self::assertStringContainsString('.home-landing__signature', $css);
        self::assertStringContainsString('max-width: 12rem', $css);
    }

    /**
     * @brief Tile icon upload must reject non SVG/WebP extensions.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testStoreTileIconUploadRejectsPng(): void
    {
        $pngPath = tempnam(sys_get_temp_dir(), 'tile-icon-');
        self::assertNotFalse($pngPath);
        $pngFile = $pngPath.'.png';
        rename($pngPath, $pngFile);
        file_put_contents($pngFile, "\x89PNG\r\n\x1a\n");

        $upload = new UploadedFile($pngFile, 'icon.png', 'image/png', null, true);

        $method = new \ReflectionMethod(HomeCustomizationService::class, 'storeTileIconUpload');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dashboard.customization_home.flash.invalid_tile_icon');
        $method->invoke($this->service, $upload, 'dashboard', null);

        @unlink($pngFile);
    }

    /**
     * @brief Tile icon upload must accept WebP files.
     *
     * @return void
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function testStoreTileIconUploadAcceptsWebp(): void
    {
        $projectDir = sys_get_temp_dir().'/home-custom-test-'.bin2hex(random_bytes(4));
        $customDir = $projectDir.'/public/images/home/custom';
        mkdir($customDir, 0775, true);

        $service = new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(HomeCustomizationRepository::class),
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', $projectDir),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            $projectDir,
        );

        $webpPath = tempnam(sys_get_temp_dir(), 'tile-icon-');
        self::assertNotFalse($webpPath);
        $webpFile = $webpPath.'.webp';
        rename($webpPath, $webpFile);
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        imagewebp($image, $webpFile);
        imagedestroy($image);

        $upload = new UploadedFile($webpFile, 'icon.webp', 'image/webp', null, true);

        $method = new \ReflectionMethod(HomeCustomizationService::class, 'storeTileIconUpload');
        $method->setAccessible(true);

        $relative = $method->invoke($service, $upload, 'dashboard', null);

        self::assertStringStartsWith('images/home/custom/quick-tile-dashboard-', $relative);
        self::assertStringEndsWith('.webp', $relative);
        self::assertFileExists($projectDir.'/public/'.$relative);

        @unlink($webpFile);
        $this->removeDirectory($projectDir);
    }

    /**
     * @brief Default signature image must resolve when stored path matches bundled asset.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveSignatureImageRelativePathReturnsDefaultAssetByDefault(): void
    {
        $service = $this->createServiceWithProjectAssets();

        $home = new HomeCustomization();
        $home->setSignatureImageRelativePath(HomeCustomizationService::DEFAULT_SIGNATURE_PATH);

        self::assertSame(
            HomeCustomizationService::DEFAULT_SIGNATURE_PATH,
            $service->resolveSignatureImageRelativePath($home)
        );
        self::assertNull($service->resolveSignatureImageRelativePath($home, false));
    }

    /**
     * @brief Deprecated stored home image paths must fall back to bundled defaults when requested.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveSignatureImageRelativePathFallsBackFromDeprecatedStoredPaths(): void
    {
        $service = $this->createServiceWithProjectAssets();

        $home = new HomeCustomization();
        $home->setSignatureImageRelativePath('images/home/hirt-stephane.webp');

        self::assertSame(
            HomeCustomizationService::DEFAULT_SIGNATURE_PATH,
            $service->resolveSignatureImageRelativePath($home)
        );
        self::assertNull($service->resolveSignatureImageRelativePath($home, false));
    }

    /**
     * @brief Null stored path must fall back to bundled generic signature on the public home.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveSignatureImageRelativePathFallsBackWhenUnset(): void
    {
        $service = $this->createServiceWithProjectAssets();
        $home = new HomeCustomization();

        self::assertSame(
            HomeCustomizationService::DEFAULT_SIGNATURE_PATH,
            $service->resolveSignatureImageRelativePath($home)
        );
        self::assertNull($service->resolveSignatureImageRelativePath($home, false));
    }

    /**
     * @brief Custom home signature uploads must resolve when the file exists.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveSignatureImageRelativePathReturnsCustomUpload(): void
    {
        $projectDir = sys_get_temp_dir().'/home-signature-test-'.bin2hex(random_bytes(4));
        $relative = 'images/home/custom/signature.webp';
        mkdir($projectDir.'/public/images/home/custom', 0775, true);
        file_put_contents($projectDir.'/public/'.$relative, 'webp');

        $service = new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(HomeCustomizationRepository::class),
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', $projectDir),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            $projectDir,
        );

        $home = new HomeCustomization();
        $home->setSignatureImageRelativePath($relative);

        self::assertSame($relative, $service->resolveSignatureImageRelativePath($home));

        $this->removeDirectory($projectDir);
    }

    /**
     * @brief Site favicon resolver must fall back to system default when unset.
     *
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testResolveSiteFaviconRelativePathUsesDefaultWhenUnset(): void
    {
        $repository = $this->createMock(HomeCustomizationRepository::class);
        $repository->method('getSingleton')->willReturn(null);

        $service = new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $repository,
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', sys_get_temp_dir()),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            sys_get_temp_dir(),
        );

        self::assertSame(HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH, $service->resolveSiteFaviconRelativePath());
        self::assertSame('image/svg+xml', $service->resolveSiteFaviconMimeType());
    }

    /**
     * @brief Missing custom favicon files must fall back to the default asset.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveSiteFaviconRelativePathUsesDefaultWhenStoredFileMissing(): void
    {
        $home = new HomeCustomization();
        $home->setSiteFaviconRelativePath('favicon/custom/site-favicon-missing.svg');

        $repository = $this->createMock(HomeCustomizationRepository::class);
        $repository->method('getSingleton')->willReturn($home);

        $service = new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $repository,
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', sys_get_temp_dir()),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            sys_get_temp_dir(),
        );

        self::assertSame(HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH, $service->resolveSiteFaviconRelativePath());
    }

    /**
     * @brief Site favicon upload must persist under favicon/custom and resolve MIME for PNG.
     *
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testStoreSiteFaviconUploadAcceptsSvg(): void
    {
        $projectDir = sys_get_temp_dir().'/home-favicon-test-'.bin2hex(random_bytes(4));
        mkdir($projectDir.'/public/favicon/custom', 0775, true);

        $service = new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(HomeCustomizationRepository::class),
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', $projectDir),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            $projectDir,
        );

        $svgPath = tempnam(sys_get_temp_dir(), 'site-favicon-');
        self::assertNotFalse($svgPath);
        $svgFile = $svgPath.'.svg';
        rename($svgPath, $svgFile);
        file_put_contents($svgFile, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>');

        $upload = new UploadedFile($svgFile, 'favicon.svg', 'image/svg+xml', null, true);

        $method = new \ReflectionMethod(HomeCustomizationService::class, 'storeSiteFaviconUpload');
        $method->setAccessible(true);

        $relative = $method->invoke($service, $upload, null);

        self::assertStringStartsWith('favicon/custom/site-favicon-', $relative);
        self::assertStringEndsWith('.svg', $relative);
        self::assertFileExists($projectDir.'/public/'.$relative);

        $home = new HomeCustomization();
        $home->setSiteFaviconRelativePath($relative);

        $repository = $this->createMock(HomeCustomizationRepository::class);
        $repository->method('getSingleton')->willReturn($home);

        $resolvingService = new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $repository,
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', $projectDir),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            $projectDir,
        );

        self::assertSame($relative, $resolvingService->resolveSiteFaviconRelativePath());
        self::assertSame('image/svg+xml', $resolvingService->resolveSiteFaviconMimeType());

        @unlink($svgFile);
        $this->removeDirectory($projectDir);
    }

    /**
     * @brief Legacy cv-symfony8 intro copies must be ignored at runtime.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveIntroTextIgnoresLegacySeedCopy(): void
    {
        $home = new HomeCustomization();
        $translation = new \App\Entity\HomeCustomizationTranslation();
        $translation->setLocale('fr');
        $translation->setIntroText('Site internet de HIRT Stéphane, Développeur, photographe amateur, blogueur.');
        $home->addTranslation($translation);

        self::assertSame('', $this->service->resolveIntroText('fr', $home));
        self::assertSame('', $this->service->resolveIntroTextForLocale($home, 'fr'));
    }

    /**
     * @brief Custom intro copies must still resolve for the landing page.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveIntroTextReturnsCustomCopy(): void
    {
        $home = new HomeCustomization();
        $translation = new \App\Entity\HomeCustomizationTranslation();
        $translation->setLocale('fr');
        $translation->setIntroText('<p>Mon accroche personnalisée</p>');
        $home->addTranslation($translation);

        self::assertSame('<p>Mon accroche personnalisée</p>', $this->service->resolveIntroText('fr', $home));
    }

    /**
     * @brief Build service bound to the real project public assets.
     *
     * @return HomeCustomizationService
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function createServiceWithProjectAssets(): HomeCustomizationService
    {
        $projectDir = dirname(__DIR__, 4);

        return new HomeCustomizationService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(HomeCustomizationRepository::class),
            new CssSanitizerService(),
            new LocaleConfigurationService(['fr', 'en'], 'fr', $projectDir),
            new RichHtmlSanitizer(),
            new HomeQuickTilePresetRegistry(),
            new ImageReencoder(),
            $projectDir,
        );
    }

    /**
     * @brief Recursively remove a directory tree.
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
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
