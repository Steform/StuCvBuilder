<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Cv\AboutBackgroundDecoration;
use App\Cv\AboutPortraitFrame;
use App\Repository\CvProfileRepository;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\Cv\AboutPresentationContract;
use App\Service\Cv\AboutPresentationDefaultContentService;
use App\Service\Cv\AboutSectionAtmospherePresetRegistry;
use App\Service\Cv\CvAboutProfileSettingsService;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Cv\CvAgeYearsCalculator;
use App\Tests\Support\CvPublicIdentityPlaceholderServiceFactory;
use App\Tests\Support\CvPdfPlaceholderTestTranslator;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Security\CssSanitizerService;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for About profile settings disk sanitization and fallback.
 * @date 2026-05-09
 * @author Stephane H.
 */
final class CvAboutProfileSettingsServiceTest extends TestCase
{
    /**
     * @brief Disk settings must clamp numeric ranges; legacy color keys are ignored.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testResolveFromContentJsonClampsAndNormalizesDiskSettings(): void
    {
        $service = $this->createService();
        $payload = [
            'aboutDiskColorInner' => '#abc',
            'aboutDiskColorOuter' => 'invalid',
            'aboutDiskBorderColor' => '#123456',
            'aboutDiskBorderOpacity' => 3,
            'aboutDiskGlowOuterOpacity' => -1,
            'aboutDiskGlowInnerOpacity' => 0.5,
            'aboutDiskGlowOuterBlurPx' => 400,
            'aboutDiskGlowInnerBlurPx' => -4,
            'aboutDiskBorderThicknessPx' => 99,
        ];

        $resolved = $service->resolveFromContentJson((string) json_encode($payload));
        $disk = $resolved['disk'];

        self::assertArrayNotHasKey('colorInner', $disk);
        self::assertArrayNotHasKey('colorOuter', $disk);
        self::assertArrayNotHasKey('borderColor', $disk);
        self::assertSame(1.0, $disk['borderOpacity']);
        self::assertSame(0.0, $disk['glowOuterOpacity']);
        self::assertSame(0.5, $disk['glowInnerOpacity']);
        self::assertSame(120, $disk['glowOuterBlurPx']);
        self::assertSame(0, $disk['glowInnerBlurPx']);
        self::assertSame(12, $disk['thicknessPx']);
    }

    /**
     * @brief Legacy ring keys must keep disk geometry compatibility.
     * @return void
     * @date 2026-05-09
     * @author Stephane H.
     */
    public function testResolveFromContentJsonUsesLegacyRingFallback(): void
    {
        $service = $this->createService();
        $payload = [
            'aboutProfilePhotoRingScale' => 1.33,
            'aboutProfilePhotoSubjectXPercent' => 64,
            'aboutProfilePhotoSubjectYPercent' => 41,
            'aboutProfilePhotoRingThicknessPx' => 6,
        ];

        $resolved = $service->resolveFromContentJson((string) json_encode($payload));
        $disk = $resolved['disk'];

        self::assertSame(1.33, $disk['scale']);
        self::assertSame(64.0, $disk['subjectX']);
        self::assertSame(41.0, $disk['subjectY']);
        self::assertSame(6, $disk['thicknessPx']);
    }

    /**
     * @brief Presentation block must expose sanitized HTML per locale without placement layouts.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testResolveFromContentJsonIncludesPresentationDefaults(): void
    {
        $service = $this->createService();
        $resolved = $service->resolveFromContentJson('{}');

        self::assertArrayHasKey('presentation', $resolved);
        self::assertStringContainsString('Votre nom', $resolved['presentation']['html']);
        self::assertArrayHasKey('htmlByLocale', $resolved['presentation']);
        self::assertArrayHasKey('fr', $resolved['presentation']['htmlByLocale']);
        self::assertStringContainsString('Votre nom', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertArrayNotHasKey('layoutDesktop', $resolved['presentation']);
        self::assertArrayNotHasKey('layoutMobile', $resolved['presentation']);
    }

    /**
     * @brief Dynamic stylesheet must not emit responsive presentation placement rules.
     * @return void
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function testBuildStylesheetCssOmitsPresentationPlacementRules(): void
    {
        $service = $this->createService();
        $settings = $service->resolveFromContentJson('{}');
        $css = $service->buildStylesheetCss($settings);

        self::assertStringNotContainsString('.cv-about-presentation', $css);
        self::assertStringContainsString('.cv-about-profile-wrap', $css);
    }

    /**
     * @brief Admin editor path must keep literal `[[cv.*]]` tokens after sanitization (no server-side substitution).
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testResolveFromContentJsonSkipsCvPlaceholdersWhenDisabled(): void
    {
        $service = $this->createService();
        $payload = [
            AboutPresentationContract::KEY_HTML_BY_LOCALE => [
                'fr' => '<p>[[cv.display_name]] [[cv.pdf]]</p>',
            ],
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Jane Doe',
            ],
        ];

        $resolved = $service->resolveFromContentJson(
            (string) json_encode($payload),
            ['fr'],
            'fr',
            null,
            false
        );

        self::assertStringContainsString('[[cv.display_name]]', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringContainsString('[[cv.pdf]]', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringContainsString('[[cv.display_name]]', $resolved['presentation']['html']);
        self::assertStringContainsString('[[cv.pdf]]', $resolved['presentation']['html']);
    }

    /**
     * @brief Default path must substitute `[[cv.display_name]]` using `cvPublicIdentity` (non-editor consumers).
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testResolveFromContentJsonAppliesCvPlaceholdersByDefault(): void
    {
        $service = $this->createService();
        $payload = [
            AboutPresentationContract::KEY_HTML_BY_LOCALE => [
                'fr' => '<p>[[cv.display_name]] [[cv.pdf]]</p>',
            ],
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Jane Doe',
            ],
        ];

        $resolved = $service->resolveFromContentJson(
            (string) json_encode($payload),
            ['fr'],
            'fr',
            null,
            true
        );

        self::assertStringNotContainsString('[[cv.display_name]]', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringContainsString('Jane Doe', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringNotContainsString('[[cv.pdf]]', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringContainsString('href="/cv/pdf"', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringContainsString('Télécharger le CV PDF', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringNotContainsString('[[cv.display_name]]', $resolved['presentation']['html']);
        self::assertStringNotContainsString('[[cv.pdf]]', $resolved['presentation']['html']);
    }

    /**
     * @brief Missing or invalid `aboutPortraitFrame` must resolve to legacy halo default.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testResolveFromContentJsonPortraitFrameDefaultsToLegacy(): void
    {
        $service = $this->createService();
        $empty = $service->resolveFromContentJson('{}');
        self::assertSame(AboutPortraitFrame::LegacyHalo, $empty['portraitFrame']);

        $bad = $service->resolveFromContentJson((string) json_encode(['aboutPortraitFrame' => 'nope']));
        self::assertSame(AboutPortraitFrame::LegacyHalo, $bad['portraitFrame']);
    }

    /**
     * @brief Stored portrait frame enum values must round-trip through resolution.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testResolveFromContentJsonAcceptsAllPortraitFrames(): void
    {
        $service = $this->createService();
        foreach (AboutPortraitFrame::cases() as $case) {
            $resolved = $service->resolveFromContentJson((string) json_encode(['aboutPortraitFrame' => $case->value]));
            self::assertSame($case, $resolved['portraitFrame']);
        }
    }

    /**
     * @brief Dynamic About stylesheet must still emit disk variables for all portrait frames.
     * @return void
     * @date 2026-05-14
     * @author Stephane H.
     */
    public function testBuildStylesheetCssIncludesDiskVariablesForAlternatePortraitFrame(): void
    {
        $service = $this->createService();
        $settings = $service->resolveFromContentJson((string) json_encode(['aboutPortraitFrame' => AboutPortraitFrame::EditorialRing->value]));
        $css = $service->buildStylesheetCss($settings);
        self::assertStringContainsString('--about-disk-enabled', $css);
        self::assertStringContainsString('--about-disk-opacity', $css);
        self::assertStringContainsString('--about-portrait-inner-rgba', $css);
        self::assertStringContainsString('var(--about-bg-secondary)', $css);
    }

    /**
     * @brief Desktop profile wrap in generated CSS must allow hover drop-shadow (overflow visible, no hidden).
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    /**
     * @brief Dot grid settings must resolve from payload and emit CSS variables when enabled.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testResolveDotsFromPayloadAndBuildStylesheetCss(): void
    {
        $service = $this->createService();
        $payload = [
            'aboutDotsOpacity' => 0.8,
            'aboutDotsGridSizePx' => 28,
            'aboutDotsDotSizePx' => 4,
            'aboutDotsMaskXPercent' => 60,
            'aboutDotsMaskYPercent' => 50,
        ];
        $resolved = $service->resolveFromContentJson((string) json_encode($payload));
        $decoration = $resolved['backgroundDecoration'];
        $dotGrid = $decoration['dotGrid'];

        self::assertSame(AboutBackgroundDecoration::DotGrid, $decoration['style']);
        self::assertArrayNotHasKey('color', $dotGrid);
        self::assertSame(0.8, $dotGrid['opacity']);
        self::assertSame(28, $dotGrid['gridSizePx']);
        self::assertSame(4, $dotGrid['dotSizePx']);
        self::assertSame(60.0, $dotGrid['maskXPercent']);
        self::assertSame(50.0, $dotGrid['maskYPercent']);

        $css = $service->buildStylesheetCss($resolved);
        self::assertStringContainsString('--about-bg-decor-dots-opacity: 0.8', $css);
        self::assertStringContainsString('--about-bg-decor-dots-size: 28px', $css);
        self::assertStringContainsString('--about-bg-decor-dots-dot-size: 4px', $css);
        self::assertStringContainsString('--about-bg-decor-enabled: 1', $css);
    }

    /**
     * @brief Empty profile JSON must apply default dot grid settings.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testResolveDotsFromEmptyPayloadUsesDefaults(): void
    {
        $service = $this->createService();
        $resolved = $service->resolveFromContentJson('{}');
        $decoration = $resolved['backgroundDecoration'];
        $dotGrid = $decoration['dotGrid'];

        self::assertSame(AboutBackgroundDecoration::DotGrid, $decoration['style']);
        self::assertArrayNotHasKey('color', $dotGrid);
        self::assertSame(0.75, $dotGrid['opacity']);
        self::assertSame(22, $dotGrid['gridSizePx']);
        self::assertSame(1, $dotGrid['dotSizePx']);
        self::assertSame(78.0, $dotGrid['maskXPercent']);
        self::assertSame(45.0, $dotGrid['maskYPercent']);
    }

    /**
     * @brief Disabled dot grid must set --about-dots-enabled to 0 in generated CSS.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testBuildStylesheetCssDotsDisabledSetsEnabledZero(): void
    {
        $service = $this->createService();
        $resolved = $service->resolveFromContentJson((string) json_encode(['aboutDotsEnabled' => false]));
        $css = $service->buildStylesheetCss($resolved);

        self::assertSame(AboutBackgroundDecoration::None, $resolved['backgroundDecoration']['style']);
        self::assertStringContainsString('--about-bg-decor-enabled: 0', $css);
    }

    /**
     * @brief Ambient particles speed factor must resolve from payload and emit CSS variable when active.
     *
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function testAmbientParticlesSpeedFactorEmitsCssVariable(): void
    {
        $service = $this->createService();
        $payload = [
            'aboutBackgroundDecoration' => 'ambient_particles',
            'aboutBgDecorParticlesIntensity' => 0.9,
            'aboutBgDecorParticlesSpeedFactor' => 1.6,
        ];
        $resolved = $service->resolveFromContentJson((string) json_encode($payload));
        $particles = $resolved['backgroundDecoration']['ambientParticles'];

        self::assertSame(AboutBackgroundDecoration::AmbientParticles, $resolved['backgroundDecoration']['style']);
        self::assertSame(0.9, $particles['intensity']);
        self::assertSame(1.6, $particles['speedFactor']);

        $css = $service->buildStylesheetCss($resolved);
        self::assertStringContainsString('--about-particles-speed-factor: 1.6', $css);
    }

    public function testBuildStylesheetCssDesktopProfileWrapUsesVisibleOverflowForHoverShadow(): void
    {
        $service = $this->createService();
        $css = $service->buildStylesheetCss($service->resolveFromContentJson('{}'));
        $wrapBlock = $this->extractProfileWrapDesktopBlock($css);

        self::assertNotSame('', $wrapBlock);
        self::assertStringContainsString('overflow: visible', $wrapBlock);
        self::assertStringNotContainsString('overflow: hidden', $wrapBlock);
        self::assertStringContainsString('height: 90%', $wrapBlock);
        self::assertStringNotContainsString('height: auto', $wrapBlock);
    }

    /**
     * @brief Missing stored path must resolve to the generic user placeholder asset.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveProfilePhotoDisplayPathUsesPlaceholderWhenMissing(): void
    {
        $service = $this->createService();

        self::assertSame(
            CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH,
            $service->resolveProfilePhotoDisplayPath(null)
        );
    }

    /**
     * @brief Custom upload paths under about/custom must be returned unchanged.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveProfilePhotoDisplayPathUsesCustomUploadPath(): void
    {
        $service = $this->createService();
        $customPath = CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT.'/photo.webp';

        self::assertSame($customPath, $service->resolveProfilePhotoDisplayPath($customPath));
    }

    /**
     * @brief Deprecated stored profile photo paths must map to the user placeholder asset.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveProfilePhotoDisplayPathMapsDeprecatedStoredPathsToPlaceholder(): void
    {
        $service = $this->createService();

        self::assertSame(
            CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH,
            $service->resolveProfilePhotoDisplayPath('images/cv/about/Stephane-HIRT.webp')
        );
        self::assertSame(
            CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH,
            $service->resolveProfilePhotoDisplayPath('images/home/hirt-stephane.webp')
        );
        self::assertFalse($service->hasUserProfilePhoto('images/cv/about/Stephane-HIRT.webp'));
    }

    /**
     * @brief resolveFromContentJson must expose placeholder path when no upload exists.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    /**
     * @brief Clearing the editor and saving must persist the default presentation skeleton per locale.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testNormalizePresentationHtmlForStorageRestoresDefaultWhenEditorIsEmpty(): void
    {
        $service = $this->createService();

        $stored = $service->normalizePresentationHtmlForStorage('<p><br></p>', 'fr');

        self::assertStringContainsString('[[cv.display_name]]', $stored);
        self::assertStringContainsString('[[cv.sought_position]]', $stored);
        self::assertStringContainsString('[[cv.city]]', $stored);
        self::assertStringContainsString('[[cv.pdf]]', $stored);
        self::assertStringContainsString('<p>', $stored);
    }

    /**
     * @brief Stored empty HTML map must resolve to default skeleton on read.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveFromContentJsonRestoresDefaultWhenStoredHtmlIsEffectivelyEmpty(): void
    {
        $service = $this->createService();
        $payload = json_encode([
            AboutPresentationContract::KEY_HTML_BY_LOCALE => ['fr' => '<p></p>'],
        ], JSON_THROW_ON_ERROR);

        $resolved = $service->resolveFromContentJson($payload, ['fr'], 'fr', 'fr', false);

        self::assertStringContainsString('[[cv.display_name]]', $resolved['presentation']['htmlByLocale']['fr']);
        self::assertStringContainsString('[[cv.sought_position]]', $resolved['presentation']['htmlByLocale']['fr']);
    }

    public function testResolveFromContentJsonUsesPlaceholderWhenNoStoredPath(): void
    {
        $service = $this->createService();
        $resolved = $service->resolveFromContentJson('{}');

        self::assertSame(CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH, $resolved['path']);
        self::assertArrayNotHasKey('xPercent', $resolved);
        self::assertStringContainsString('Votre nom', $resolved['presentation']['htmlByLocale']['fr'] ?? '');
        self::assertStringContainsString('href="/cv/pdf"', $resolved['presentation']['htmlByLocale']['fr'] ?? '');
    }

    /**
     * @brief Global CV placeholder mode must expose generic user placeholder photo path.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveFromContentJsonUsesPlaceholderPathInGlobalPlaceholderMode(): void
    {
        $service = $this->createService(true);
        $resolved = $service->resolveFromContentJson((string) json_encode([
            'aboutProfilePhotoPath' => CvAboutProfileSettingsService::ABOUT_CUSTOM_UPLOAD_ROOT.'/x.webp',
        ]));

        self::assertSame(CvAboutProfileSettingsService::PROFILE_PHOTO_PLACEHOLDER_PATH, $resolved['path']);
        self::assertTrue($resolved['photoPlaceholder']);
    }

    /**
     * @brief Global placeholder mode must still emit profile wrap placement CSS from defaults.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testBuildStylesheetCssInGlobalPlaceholderModeIncludesProfileWrap(): void
    {
        $service = $this->createService(true);
        $resolved = $service->resolveFromContentJson('{}');
        $css = $service->buildStylesheetCss($resolved);

        self::assertStringContainsString('.cv-about-profile-wrap', $css);
        self::assertStringNotContainsString('.cv-about-profile-photo', $css);
    }

    /**
     * @brief Preset atmosphere style must override legacy background fields on resolve.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testResolveBackgroundUsesPresetOverLegacyFields(): void
    {
        $service = $this->createService();
        $payload = [
            'aboutSectionAtmosphereStyle' => 'style_2',
            'aboutBackgroundPrimary' => '#ffffff',
            'aboutBackgroundSecondary' => '#ffffff',
            'aboutHaloStrength' => 0.99,
        ];

        $resolved = $service->resolveFromContentJson((string) json_encode($payload));
        $background = $resolved['background'];

        self::assertSame('style_2', $background['atmosphereStyle']);
        self::assertSame('#050508', $background['primary']);
        self::assertSame(0.0, $background['haloStrength']);
        self::assertNotSame('', $background['sectionBackgroundOverride']);
    }

    /**
     * @brief Style 1 must expose legacy defaults without background override block.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testStyle1BackgroundMatchesLegacyInStylesheet(): void
    {
        $service = $this->createService();
        $resolved = $service->resolveFromContentJson((string) json_encode([
            'aboutSectionAtmosphereStyle' => 'style_1',
        ]));
        $css = $service->buildStylesheetCss($resolved);

        self::assertStringContainsString('--about-bg-primary: #010a22', $css);
        self::assertStringContainsString('--about-halo-strength: 0.65', $css);
        self::assertStringNotContainsString('background: #08080c', $css);
    }

    /**
     * @brief Preset with section background override must emit sanitized declarations in dynamic CSS.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testStyle2StylesheetContainsBackgroundOverrideWithSemicolon(): void
    {
        $service = $this->createService();
        $resolved = $service->resolveFromContentJson((string) json_encode([
            'aboutSectionAtmosphereStyle' => 'style_2',
        ]));
        $css = $service->buildStylesheetCss($resolved);
        $globalCss = $this->extractCssBeforeFirstDesktopMediaQuery($css);

        self::assertStringContainsString('background: #08080c;', $css);
        self::assertStringContainsString('background: #08080c;', $globalCss);
        self::assertStringContainsString('.cv-custom-section--about', $css);
    }

    /**
     * @brief Atmosphere CSS variables must apply on all viewports, before the desktop-only media block.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testAtmosphereVariablesAreGlobalNotDesktopOnly(): void
    {
        $service = $this->createService();
        $resolved = $service->resolveFromContentJson((string) json_encode([
            'aboutSectionAtmosphereStyle' => 'style_1',
        ]));
        $css = $service->buildStylesheetCss($resolved);
        $globalCss = $this->extractCssBeforeFirstDesktopMediaQuery($css);

        self::assertStringContainsString('--about-bg-primary: #010a22', $globalCss);
        self::assertStringContainsString('--about-bg-secondary: #03215a', $globalCss);
        self::assertStringContainsString('--about-halo-strength: 0.65', $globalCss);
        self::assertStringNotContainsString('--about-disk-enabled', $globalCss);
    }

    /**
     * @brief Build service with optional global CV placeholder mode.
     *
     * @param bool $cvPlaceholderActive When true, simulates empty cv_profile table.
     * @return CvAboutProfileSettingsService
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function createService(bool $cvPlaceholderActive = false): CvAboutProfileSettingsService
    {
        $repository = $this->createMock(CvProfileRepository::class);
        $repository->method('count')->with([])->willReturn($cvPlaceholderActive ? 0 : 1);

        $richHtmlSanitizer = new RichHtmlSanitizer();
        $translator = CvPdfPlaceholderTestTranslator::create();

        return new CvAboutProfileSettingsService(
            $richHtmlSanitizer,
            CvPublicIdentityPlaceholderServiceFactory::create(),
            new CustomizationPlaceholderStateService($repository),
            new AboutPresentationDefaultContentService($richHtmlSanitizer),
            new AboutSectionAtmospherePresetRegistry(new CssSanitizerService()),
            new CssSanitizerService(),
        );
    }

    /**
     * @brief Extract `.cv-about-profile-wrap` rules inside the desktop `@media (min-width: 992px)` block.
     * @param string $css Full generated stylesheet.
     * @return string Matched declaration block or empty string.
     * @date 2026-05-15
     * @author Stephane H.
     */
    private function extractProfileWrapDesktopBlock(string $css): string
    {
        $pattern = '/@media\s*\(\s*min-width:\s*992px\s*\)\s*\{.*?\.cv-about-profile-wrap\s*\{([^}]*)\}/s';
        if (preg_match($pattern, $css, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    /**
     * @brief Return CSS emitted before the first desktop-only `@media (min-width: 992px)` block.
     *
     * @param string $css Full generated stylesheet.
     * @return string Prefix of the stylesheet (global atmosphere rules).
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function extractCssBeforeFirstDesktopMediaQuery(string $css): string
    {
        $position = strpos($css, '@media (min-width: 992px)');

        return $position === false ? $css : substr($css, 0, $position);
    }
}

