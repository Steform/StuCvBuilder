<?php

namespace App\Service\Cv;

use App\Cv\AboutBackgroundDecoration;
use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\AboutPortraitFrame;
use App\Cv\SectionBackgroundContract;
use App\Cv\SectionTransition;
use App\Cv\SectionTransitionContract;
use App\Cv\SituationBackgroundTexture;
use App\Service\Customization\CustomizationPlaceholderStateService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Security\CssSanitizerService;

/**
 * Resolves CV About profile photo placement settings from persisted JSON payload.
 *
 * `[[cv.*]]` placeholders: when {@see self::resolveFromContentJson} is called with `$applyCvIdentityPlaceholders === false`
 * (admin About / CKEditor), presentation HTML stays as sanitized literals. Public CV rendering applies substitution in
 * {@see \App\Service\Cv\CvResolverService::sanitizeAboutPresentationHtmlInPayload} instead.
 */
class CvAboutProfileSettingsService
{
    public function __construct(
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
        private readonly CvPublicIdentityPlaceholderService $cvPublicIdentityPlaceholderService,
        private readonly CustomizationPlaceholderStateService $placeholderStateService,
        private readonly AboutPresentationDefaultContentService $aboutPresentationDefaultContent,
        private readonly AboutSectionAtmospherePresetRegistry $aboutSectionAtmospherePresetRegistry,
        private readonly CssSanitizerService $cssSanitizer,
    ) {
    }

    public const PROFILE_PHOTO_PLACEHOLDER_PATH = 'images/cv/about/user-placeholder.webp';

    /** @var string About profile photo upload directory relative to public/. */
    public const ABOUT_CUSTOM_UPLOAD_ROOT = 'images/cv/about/custom';

    /** @var list<string> Deprecated stored profile photo paths treated as unset. */
    public const DEPRECATED_PROFILE_PHOTO_PATHS = [
        'images/cv/about/Stephane-HIRT.webp',
        'images/home/hirt-stephane.webp',
    ];

    private const DEFAULT_X_PERCENT = 80.0;

    private const DEFAULT_WIDTH_PX = 460;

    private const MIN_X_PERCENT = 10.0;

    private const MAX_X_PERCENT = 90.0;

    private const MIN_WIDTH_PX = 120;

    private const MAX_WIDTH_PX = 1200;

    private const DEFAULT_SUBJECT_X_PERCENT = 50.0;

    private const DEFAULT_SUBJECT_Y_PERCENT = 50.0;

    private const MIN_SUBJECT_PERCENT = 0.0;

    private const MAX_SUBJECT_PERCENT = 100.0;

    private const DEFAULT_RING_SCALE = 1.0;

    private const MIN_RING_SCALE = 0.6;

    private const MAX_RING_SCALE = 1.8;

    private const DEFAULT_RING_THICKNESS_PX = 3;

    private const MIN_RING_THICKNESS_PX = 1;

    private const MAX_RING_THICKNESS_PX = 12;

    private const DEFAULT_BACKGROUND_PRIMARY_HEX = '#010a22';

    private const DEFAULT_BACKGROUND_SECONDARY_HEX = '#03215a';

    private const DEFAULT_HALO_STRENGTH = 0.65;

    private const DEFAULT_DISK_ENABLED = true;

    private const DEFAULT_DISK_OPACITY = 0.72;

    private const DEFAULT_DISK_COLOR_INNER_HEX = '#88ccff';

    private const DEFAULT_DISK_COLOR_OUTER_HEX = '#1450c9';

    private const DEFAULT_DISK_BORDER_COLOR_HEX = '#addfff';

    private const DEFAULT_DISK_BORDER_OPACITY = 0.22;

    private const DEFAULT_DISK_GLOW_OUTER_OPACITY = 0.30;

    private const DEFAULT_DISK_GLOW_INNER_OPACITY = 0.22;

    private const DEFAULT_DISK_GLOW_OUTER_BLUR_PX = 48;

    private const DEFAULT_DISK_GLOW_INNER_BLUR_PX = 54;

    private const MIN_DISK_GLOW_OUTER_BLUR_PX = 0;

    private const MAX_DISK_GLOW_OUTER_BLUR_PX = 120;

    private const MIN_DISK_GLOW_INNER_BLUR_PX = 0;

    private const MAX_DISK_GLOW_INNER_BLUR_PX = 140;

    private const DEFAULT_DOTS_COLOR_HEX = '#88ccff';

    private const DEFAULT_DOTS_OPACITY = 0.75;

    private const DEFAULT_DOTS_GRID_SIZE_PX = 22;

    private const DEFAULT_DOTS_MASK_X_PERCENT = 78.0;

    private const DEFAULT_DOTS_MASK_Y_PERCENT = 45.0;

    private const MIN_DOTS_GRID_PX = 14;

    private const MAX_DOTS_GRID_PX = 36;

    private const DEFAULT_DOTS_DOT_SIZE_PX = 1;

    private const MIN_DOTS_DOT_SIZE_PX = 1;

    private const MAX_DOTS_DOT_SIZE_PX = 8;

    private const DEFAULT_ADAPTIVE_INTENSITY = 1.0;

    private const MIN_ADAPTIVE_INTENSITY = 0.4;

    private const MAX_ADAPTIVE_INTENSITY = 1.0;

    private const DEFAULT_CODE_RAIN_SPEED_FACTOR = 1.0;

    private const MIN_CODE_RAIN_SPEED_FACTOR = 0.6;

    private const MAX_CODE_RAIN_SPEED_FACTOR = 1.4;

    private const DEFAULT_PARTICLES_SPEED_FACTOR = 1.0;

    private const MIN_PARTICLES_SPEED_FACTOR = 0.4;

    private const MAX_PARTICLES_SPEED_FACTOR = 6.0;

    private const DEFAULT_HATCH_OPACITY = 0.35;

    private const DEFAULT_HATCH_COLOR_HEX = '#88ccff';

    private const DEFAULT_HATCH_ANGLE_DEG = 135.0;

    private const DEFAULT_HATCH_SPACING_PX = 12;

    private const MIN_HATCH_SPACING_PX = 4;

    private const MAX_HATCH_SPACING_PX = 40;

    private const DEFAULT_HATCH_LINE_WIDTH_PX = 1.0;

    private const MIN_HATCH_LINE_WIDTH_PX = 0.5;

    private const MAX_HATCH_LINE_WIDTH_PX = 4.0;

    private const DEFAULT_HATCH_MASK_X_PERCENT = 78.0;

    private const DEFAULT_HATCH_MASK_Y_PERCENT = 45.0;

    private const DEFAULT_FINE_GRID_OPACITY = 0.22;

    private const DEFAULT_FINE_GRID_COLOR_HEX = '#88ccff';

    private const DEFAULT_FINE_GRID_STEP_PX = 24;

    private const MIN_FINE_GRID_STEP_PX = 12;

    private const MAX_FINE_GRID_STEP_PX = 48;

    private const DEFAULT_FINE_GRID_LINE_WIDTH_PX = 1.0;

    private const MIN_FINE_GRID_LINE_WIDTH_PX = 0.5;

    private const MAX_FINE_GRID_LINE_WIDTH_PX = 2.0;

    private const DEFAULT_FINE_GRID_MASK_X_PERCENT = 78.0;

    private const DEFAULT_FINE_GRID_MASK_Y_PERCENT = 45.0;

    /**
     * @brief Sanitize presentation HTML for persistence; when effectively empty, store the default skeleton for the locale.
     *
     * @param string $raw Submitted HTML from the admin editor.
     * @param string $locale Locale code used for the default skeleton translation.
     * @return string Sanitized HTML to persist in `aboutPresentationHtmlByLocale`.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function normalizePresentationHtmlForStorage(string $raw, string $locale): string
    {
        $sanitized = $this->richHtmlSanitizer->sanitize($raw);
        if ($this->richHtmlSanitizer->isEffectivelyEmpty($sanitized)) {
            $sanitized = $this->aboutPresentationDefaultContent->buildSanitizedHtmlForLocale($locale);
        }

        return $this->richHtmlSanitizer->capitalizePresentationHeadingFirstLetters($sanitized);
    }

    /**
     * @brief Resolve About profile settings including background, disk, and profile photo path with safe defaults.
     * @param string $contentJson CvProfile JSON payload.
     * @param list<string> $activeLocalesForPresentation Active locales for About presentation HTML map (empty uses defaultLocale only).
     * @param string $defaultLocaleForPresentation Default locale key for legacy migration and primary `html` preview.
     * @param string|null $presentationDisplayLocale Preferred locale for projected `presentation.html`; null uses default locale.
     * @param bool $applyCvIdentityPlaceholders When true, replace `[[cv.*]]` in presentation HTML after sanitize; false keeps literals (admin editor).
     * @return array{path: string, photoPlaceholder: bool, portraitFrame: AboutPortraitFrame, background: array{primary: string, secondary: string, haloStrength: float}, disk: array{enabled: bool, scale: float, opacity: float, subjectX: float, subjectY: float, thicknessPx: int, borderOpacity: float, glowOuterOpacity: float, glowInnerOpacity: float, glowOuterBlurPx: int, glowInnerBlurPx: int}, backgroundDecoration: array{style: AboutBackgroundDecoration, dotGrid: array{color: string, opacity: float, gridSizePx: int, dotSizePx: int, maskXPercent: float, maskYPercent: float}, depthFade: array{opacity: float, angleDeg: float, color: string, maskXPercent: float, maskYPercent: float, maskSoftnessPercent: float}, diagonalHatch: array{opacity: float, color: string, angleDeg: float, spacingPx: int, lineWidthPx: float, maskXPercent: float, maskYPercent: float}, subtleMesh: array{opacity: float, color: string, centerXPercent: float, centerYPercent: float, scalePercent: float}, fineGrid: array{color: string, opacity: float, stepPx: int, lineWidthPx: float, maskXPercent: float, maskYPercent: float}, accentGuides: array{color: string, opacity: float, guideXPercent: float, guideYPercent: float, lineWidthPx: float, maskXPercent: float, maskYPercent: float}}, ring: array{subjectX: float, subjectY: float, scale: float, thicknessPx: int}, presentation: array{html: string, htmlByLocale: array<string, string>}}
     * @date 2026-05-23
     * @author Stephane H.
     */
    public function resolveFromContentJson(
        string $contentJson,
        array $activeLocalesForPresentation = [],
        string $defaultLocaleForPresentation = 'fr',
        ?string $presentationDisplayLocale = null,
        bool $applyCvIdentityPlaceholders = true
    ): array {
        $payload = $this->decodeJsonPayload($contentJson);
        $isPlaceholderMode = $this->placeholderStateService->shouldUsePlaceholderMode($payload);
        $path = $this->resolveProfilePhotoDisplayPath(
            $isPlaceholderMode ? null : ($payload['aboutProfilePhotoPath'] ?? null)
        );

        $locales = $activeLocalesForPresentation !== [] ? $activeLocalesForPresentation : [$defaultLocaleForPresentation];

        return [
            'path' => $path,
            'photoPlaceholder' => $isPlaceholderMode,
            'hasUserProfilePhoto' => $this->hasUserProfilePhoto(
                $isPlaceholderMode ? null : ($payload['aboutProfilePhotoPath'] ?? null)
            ),
            'portraitFrame' => AboutPortraitFrame::fromStored($payload['aboutPortraitFrame'] ?? null),
            'background' => $this->resolveBackgroundFromPayload($payload),
            'disk' => $this->resolveDiskFromPayload($payload),
            'backgroundDecoration' => $this->resolveBackgroundDecorationFromPayload($payload),
            'ring' => $this->resolveRingFromPayload($payload),
            'presentation' => $this->resolvePresentationFromPayload(
                $payload,
                $locales,
                $defaultLocaleForPresentation,
                $presentationDisplayLocale,
                $applyCvIdentityPlaceholders
            ),
            'sectionPattern' => AboutSectionPatternCustomizationContract::fromPayload($payload),
        ];
    }

    /**
     * @brief Build About section preview payload per locale for the admin dashboard.
     *
     * @param string $contentJson Raw CvProfile JSON.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Default locale code.
     * @param string $patternLeftSvg Rendered left pattern SVG markup.
     * @param string $patternRightSvg Rendered right pattern SVG markup.
     * @return array<string, array{aboutProfilePhotoDisplayPath: string, aboutPresentationHtml: string, aboutPatternLeftSvgMarkup: string, aboutPatternRightSvgMarkup: string}>
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function buildAdminPreviewPayloadByLocale(
        string $contentJson,
        array $activeLocales,
        string $defaultLocale,
        string $patternLeftSvg,
        string $patternRightSvg,
    ): array {
        $previewByLocale = [];
        foreach ($activeLocales as $locale) {
            if (!is_string($locale) || $locale === '') {
                continue;
            }

            $settings = $this->resolveFromContentJson(
                $contentJson,
                $activeLocales,
                $defaultLocale,
                $locale,
                true
            );

            $previewByLocale[$locale] = [
                'aboutProfilePhotoDisplayPath' => $settings['path'],
                'aboutPresentationHtml' => is_string($settings['presentation']['html'] ?? null)
                    ? $settings['presentation']['html']
                    : '',
                'aboutPatternLeftSvgMarkup' => $patternLeftSvg,
                'aboutPatternRightSvgMarkup' => $patternRightSvg,
            ];
        }

        return $previewByLocale;
    }

    /**
     * @brief Build stylesheet for About and Situation tint variables; About overrides on all viewports; disk, decor, and photo layout from 992px up.
     *
     * @param array<string, mixed> $settings Resolved settings from {@see resolveFromContentJson()}.
     * @return string Generated CSS for {@code /css/cv-about-profile.css}.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function buildStylesheetCss(array $settings): string
    {
        $left = $this->formatCssNumber(self::DEFAULT_X_PERCENT);
        $widthPx = $this->formatCssInteger(self::DEFAULT_WIDTH_PX);
        $background = isset($settings['background']) && is_array($settings['background']) ? $settings['background'] : [];
        $disk = isset($settings['disk']) && is_array($settings['disk']) ? $settings['disk'] : [];
        if ($disk === [] && isset($settings['ring']) && is_array($settings['ring'])) {
            $legacyRing = $settings['ring'];
            $disk = [
                'enabled' => self::DEFAULT_DISK_ENABLED,
                'scale' => (float) ($legacyRing['scale'] ?? self::DEFAULT_RING_SCALE),
                'opacity' => self::DEFAULT_DISK_OPACITY,
                'subjectX' => (float) ($legacyRing['subjectX'] ?? self::DEFAULT_SUBJECT_X_PERCENT),
                'subjectY' => (float) ($legacyRing['subjectY'] ?? self::DEFAULT_SUBJECT_Y_PERCENT),
                'thicknessPx' => (int) ($legacyRing['thicknessPx'] ?? self::DEFAULT_RING_THICKNESS_PX),
                'borderOpacity' => self::DEFAULT_DISK_BORDER_OPACITY,
                'glowOuterOpacity' => self::DEFAULT_DISK_GLOW_OUTER_OPACITY,
                'glowInnerOpacity' => self::DEFAULT_DISK_GLOW_INNER_OPACITY,
                'glowOuterBlurPx' => self::DEFAULT_DISK_GLOW_OUTER_BLUR_PX,
                'glowInnerBlurPx' => self::DEFAULT_DISK_GLOW_INNER_BLUR_PX,
            ];
        }

        $backgroundPrimary = $this->sanitizeHexColor(
            $background['primary'] ?? self::DEFAULT_BACKGROUND_PRIMARY_HEX,
            self::DEFAULT_BACKGROUND_PRIMARY_HEX
        );
        $backgroundSecondary = $this->sanitizeHexColor(
            $background['secondary'] ?? self::DEFAULT_BACKGROUND_SECONDARY_HEX,
            self::DEFAULT_BACKGROUND_SECONDARY_HEX
        );
        $haloStrength = $this->formatCssAlpha(
            $this->sanitizeDecimalRange(
                $background['haloStrength'] ?? null,
                self::DEFAULT_HALO_STRENGTH,
                0.0,
                1.0
            )
        );
        $subjectX = $this->formatCssNumber((float) ($disk['subjectX'] ?? self::DEFAULT_SUBJECT_X_PERCENT));
        $subjectY = $this->formatCssNumber((float) ($disk['subjectY'] ?? self::DEFAULT_SUBJECT_Y_PERCENT));
        $ringScale = $this->formatCssNumber((float) ($disk['scale'] ?? self::DEFAULT_RING_SCALE));
        $ringThickness = $this->formatCssInteger((int) ($disk['thicknessPx'] ?? self::DEFAULT_RING_THICKNESS_PX));
        $diskOpacity = $this->formatCssAlpha(
            $this->sanitizeDecimalRange(
                $disk['opacity'] ?? null,
                self::DEFAULT_DISK_OPACITY,
                0.0,
                1.0
            )
        );
        $diskEnabled = !array_key_exists('enabled', $disk) || (bool) $disk['enabled'];
        $diskVisibility = $diskEnabled ? '1' : '0';
        $portraitAdaptiveCssVars = $this->formatPortraitAdaptiveCssVariableLines($disk);
        $diskGlowOuterBlur = $this->formatCssInteger(
            $this->sanitizeInt(
                $disk['glowOuterBlurPx'] ?? null,
                self::DEFAULT_DISK_GLOW_OUTER_BLUR_PX,
                self::MIN_DISK_GLOW_OUTER_BLUR_PX,
                self::MAX_DISK_GLOW_OUTER_BLUR_PX
            )
        );
        $diskGlowInnerBlur = $this->formatCssInteger(
            $this->sanitizeInt(
                $disk['glowInnerBlurPx'] ?? null,
                self::DEFAULT_DISK_GLOW_INNER_BLUR_PX,
                self::MIN_DISK_GLOW_INNER_BLUR_PX,
                self::MAX_DISK_GLOW_INNER_BLUR_PX
            )
        );

        $backgroundDecoration = isset($settings['backgroundDecoration']) && is_array($settings['backgroundDecoration'])
            ? $settings['backgroundDecoration']
            : $this->resolveBackgroundDecorationFromPayload([]);
        $bgDecorCssVars = $this->buildBackgroundDecorationCssVariableLines($backgroundDecoration);

        $atmosphereVariablesCss = <<<CSS
.cv-custom-section--about {
    --about-bg-primary: {$backgroundPrimary};
    --about-bg-secondary: {$backgroundSecondary};
    --about-halo-strength: {$haloStrength};
{$portraitAdaptiveCssVars}}

CSS;

        $atmosphereOverrideCss = $this->buildAboutSectionAtmosphereOverrideCss($background);

        $desktopCss = <<<CSS
@media (min-width: 992px) {
    .cv-custom-section--about {
        --about-disk-enabled: {$diskVisibility};
        --about-disk-opacity: {$diskOpacity};
        --about-disk-glow-outer-blur: {$diskGlowOuterBlur}px;
        --about-disk-glow-inner-blur: {$diskGlowInnerBlur}px;
{$bgDecorCssVars}    }

    .cv-about-profile-wrap {
        left: {$left}%;
        width: {$widthPx}px;
        height: 90%;
        max-height: 90%;
        overflow: visible;
        --about-subject-x: {$subjectX};
        --about-subject-y: {$subjectY};
        --about-ring-scale: {$ringScale};
        --about-ring-thickness: {$ringThickness}px;
    }
}

CSS;

        return $atmosphereVariablesCss.$atmosphereOverrideCss.$desktopCss;
    }

    /**
     * @brief Emit per-section background CSS variables (legacy stacked gradient + texture).
     *
     * @param array<string, array<string, mixed>> $sectionBackgrounds Normalized map from {@see SectionBackgroundContract::normalizeMap()}.
     * @param string $aboutPrimary Resolved About primary hex.
     * @param string $aboutSecondary Resolved About secondary hex.
     * @return string CSS variable rules for all eligible CV sections.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function buildSectionBackgroundVariablesCss(
        array $sectionBackgrounds,
        string $aboutPrimary,
        string $aboutSecondary,
    ): string {
        $aboutPrimarySafe = $this->sanitizeHexColor($aboutPrimary, self::DEFAULT_BACKGROUND_PRIMARY_HEX);
        $aboutSecondarySafe = $this->sanitizeHexColor($aboutSecondary, self::DEFAULT_BACKGROUND_SECONDARY_HEX);
        $chunks = [];

        foreach (SectionTransitionContract::ELIGIBLE_SECTION_KEYS as $sectionKey) {
            $block = is_array($sectionBackgrounds[$sectionKey] ?? null)
                ? $sectionBackgrounds[$sectionKey]
                : SectionBackgroundContract::defaultBlockForSection();

            $primary = $aboutPrimarySafe;
            $secondary = $aboutSecondarySafe;
            if (($block['colorMode'] ?? '') === SectionBackgroundContract::COLOR_MODE_CUSTOM) {
                $customPrimary = SectionBackgroundContract::sanitizeHexColor($block['primary'] ?? null);
                $customSecondary = SectionBackgroundContract::sanitizeHexColor($block['secondary'] ?? null);
                if ($customPrimary !== null && $customSecondary !== null) {
                    $primary = $this->sanitizeHexColor($customPrimary, $aboutPrimarySafe);
                    $secondary = $this->sanitizeHexColor($customSecondary, $aboutSecondarySafe);
                }
            } elseif (($block['colorMode'] ?? '') === SectionBackgroundContract::COLOR_MODE_ABOUT) {
                $adjustPercent = SectionBackgroundContract::normalizeAboutColorAdjustPercent(
                    $block['aboutColorAdjustPercent'] ?? null
                );
                if ($adjustPercent !== 0) {
                    $primary = SectionBackgroundContract::adjustHexByAboutTone($primary, $adjustPercent);
                    $secondary = SectionBackgroundContract::adjustHexByAboutTone($secondary, $adjustPercent);
                }
            }

            $intensityLight = SectionBackgroundContract::normalizeIntensity(
                $block['filterIntensityLight'] ?? null,
                SectionBackgroundContract::DEFAULT_FILTER_INTENSITY_LIGHT
            );
            $intensityDark = SectionBackgroundContract::normalizeIntensity(
                $block['filterIntensityDark'] ?? null,
                SectionBackgroundContract::DEFAULT_FILTER_INTENSITY_DARK
            );
            $legacyLight = SectionBackgroundContract::intensityToLegacyTextureVars($intensityLight, $secondary, false);
            $legacyDark = SectionBackgroundContract::intensityToLegacyTextureVars($intensityDark, $secondary, true);
            $texture = SituationBackgroundTexture::fromStored($block['texture'] ?? null);
            $texturePath = '/'.$texture->relativeAssetPath();
            $tile = $texture->textureTileSizePx();

            $chunks[] = <<<CSS
.cv-custom-section--{$sectionKey} {
    --cv-{$sectionKey}-bg-primary: {$primary};
    --cv-{$sectionKey}-bg-secondary: {$secondary};
    --cv-{$sectionKey}-texture-image: url("{$texturePath}");
    --cv-{$sectionKey}-texture-overlay-light: {$legacyLight['overlayRgba']};
    --cv-{$sectionKey}-texture-overlay-dark: {$legacyDark['overlayRgba']};
    --cv-{$sectionKey}-texture-tile-w: {$tile['widthPx']}px;
    --cv-{$sectionKey}-texture-tile-h: {$tile['heightPx']}px;
}

CSS;
        }

        return implode('', $chunks);
    }

    /**
     * @brief Emit per-texture tile overrides for the active BEM modifier (optional; base vars on section root).
     *
     * @param string $sectionKey Eligible section BEM slug (situation, experience, …).
     * @param SituationBackgroundTexture $texture Resolved profile texture.
     * @return string CSS rules for the active texture modifier class.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function buildSectionTextureLayerCss(
        string $sectionKey,
        SituationBackgroundTexture $texture,
    ): string {
        $modifierClass = '.cv-custom-section--'.$sectionKey.'--texture-'.$texture->value;
        $texturePath = '/'.$texture->relativeAssetPath();
        $tile = $texture->textureTileSizePx();

        return <<<CSS

{$modifierClass} {
    --cv-{$sectionKey}-texture-image: url("{$texturePath}");
    --cv-{$sectionKey}-texture-tile-w: {$tile['widthPx']}px;
    --cv-{$sectionKey}-texture-tile-h: {$tile['heightPx']}px;
}

CSS;
    }

    /**
     * @brief @deprecated Use {@see buildSectionTextureLayerCss()}; mask fades removed in favor of ::after transitions.
     *
     * @param string $sectionKey Eligible section BEM slug.
     * @param SituationBackgroundTexture $texture Resolved profile texture.
     * @param SectionTransition $transition Unused; kept for call-site compatibility.
     * @param bool $isIncoming Unused; kept for call-site compatibility.
     * @return string CSS rules for the active texture modifier class.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function buildSectionTextureMaskCss(
        string $sectionKey,
        SituationBackgroundTexture $texture,
        SectionTransition $transition,
        bool $isIncoming,
    ): string {
        return $this->buildSectionTextureLayerCss($sectionKey, $texture);
    }

    /**
     * @brief Emit active Situation texture layer variables.
     *
     * @param SituationBackgroundTexture $texture Resolved profile texture.
     * @param SectionTransition $exitTransition Unused; kept for call-site compatibility.
     * @return string CSS rules for the active texture modifier class.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function buildSituationTextureMaskCss(
        SituationBackgroundTexture $texture,
        SectionTransition $exitTransition = SectionTransition::FadeVertical,
    ): string {
        return $this->buildSectionTextureLayerCss('situation', $texture);
    }

    /**
     * @brief Emit active Experience texture layer variables.
     *
     * @param SituationBackgroundTexture $texture Resolved profile texture.
     * @param SectionTransition $incomingTransition Unused; kept for call-site compatibility.
     * @return string CSS rules for the active texture modifier class.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function buildExperienceTextureMaskCss(
        SituationBackgroundTexture $texture,
        SectionTransition $incomingTransition = SectionTransition::FadeVertical,
    ): string {
        return $this->buildSectionTextureLayerCss('experience', $texture);
    }

    /**
     * @brief Build texture layer CSS for all eligible sections (no mask-image on motif).
     *
     * @param array<string, array<string, mixed>> $sectionBackgrounds Normalized section backgrounds map.
     * @param array<string, string> $sectionTransitions Unused; section fades use {@code ::after} only.
     * @return string Concatenated texture layer CSS blocks.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function buildAllSectionTextureLayerCss(array $sectionBackgrounds, array $sectionTransitions = []): string
    {
        $css = '';

        foreach (SectionTransitionContract::ELIGIBLE_SECTION_KEYS as $sectionKey) {
            $block = is_array($sectionBackgrounds[$sectionKey] ?? null)
                ? $sectionBackgrounds[$sectionKey]
                : SectionBackgroundContract::defaultBlockForSection();
            $texture = SituationBackgroundTexture::fromStored($block['texture'] ?? null);
            $css .= $this->buildSectionTextureLayerCss($sectionKey, $texture);
        }

        return $css;
    }

    /**
     * @brief @deprecated Alias for {@see buildAllSectionTextureLayerCss()}.
     *
     * @param array<string, array<string, mixed>> $sectionBackgrounds Normalized section backgrounds map.
     * @param array<string, string> $sectionTransitions Normalized transition slugs per section.
     * @return string Concatenated texture layer CSS blocks.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function buildAllSectionTextureMaskCss(array $sectionBackgrounds, array $sectionTransitions): string
    {
        return $this->buildAllSectionTextureLayerCss($sectionBackgrounds, $sectionTransitions);
    }

    /**
     * @brief Append section background override when preset or custom atmosphere CSS applies (all viewports).
     *
     * @param array<string, mixed> $background Resolved background block from {@see resolveBackgroundFromPayload()}.
     * @return string Additional CSS rules for {@code .cv-custom-section--about} or empty string.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function buildAboutSectionAtmosphereOverrideCss(array $background): string
    {
        $override = is_string($background['sectionBackgroundOverride'] ?? null)
            ? trim((string) $background['sectionBackgroundOverride'])
            : '';
        if ($override === '') {
            return '';
        }

        $indented = preg_replace('/^/m', '    ', $override) ?? $override;

        return <<<CSS

.cv-custom-section--about {
{$indented}
}

CSS;
    }

    /**
     * @brief Resolve sanitized About presentation HTML per locale and clamped desktop/mobile layouts from JSON payload.
     * @param array<string, mixed> $payload Decoded profile payload.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param string $defaultLocale Primary locale for legacy migration and `html` preview field.
     * @param string|null $displayLocalePreferred Locale for scalar `html` pick; null uses default locale.
     * @param bool $applyCvIdentityPlaceholders When false, skip `[[cv.*]]` replacement so editors keep literal tokens.
     * @return array{html: string, htmlByLocale: array<string, string>}
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function resolvePresentationFromPayload(
        array $payload,
        array $activeLocales,
        string $defaultLocale,
        ?string $displayLocalePreferred = null,
        bool $applyCvIdentityPlaceholders = true
    ): array {
        $rawByLocale = AboutPresentationContract::htmlByLocaleFromStoredPayload($payload, $activeLocales, $defaultLocale);
        $isGlobalPlaceholder = $this->placeholderStateService->shouldUsePlaceholderMode($payload);
        $htmlByLocale = [];
        foreach ($activeLocales as $loc) {
            $raw = $rawByLocale[$loc] ?? '';
            if ($isGlobalPlaceholder) {
                $htmlByLocale[$loc] = $this->richHtmlSanitizer->capitalizePresentationHeadingFirstLetters(
                    $this->aboutPresentationDefaultContent->buildSanitizedHtmlForLocale($loc)
                );

                continue;
            }

            $htmlByLocale[$loc] = $this->normalizePresentationHtmlForStorage(is_string($raw) ? $raw : '', $loc);
        }

        $display = ($displayLocalePreferred !== null && $displayLocalePreferred !== '')
            ? $displayLocalePreferred
            : $defaultLocale;
        if ($applyCvIdentityPlaceholders) {
            $now = new \DateTimeImmutable('now');
            $afterPlaceholders = $this->cvPublicIdentityPlaceholderService->applyToSanitizedPresentation(
                $htmlByLocale,
                $payload,
                $display,
                $activeLocales,
                $defaultLocale,
                $now
            );
            $htmlByLocale = $afterPlaceholders['htmlByLocale'];
            $html = $afterPlaceholders['html'];
        } else {
            $html = AboutPresentationContract::pickPresentationHtmlForLocale(
                $htmlByLocale,
                $display,
                $defaultLocale,
                $activeLocales
            );
        }

        return [
            'html' => $html,
            'htmlByLocale' => $htmlByLocale,
        ];
    }

    /**
     * @brief Resolve About background colors and halo intensity from payload with strict fallbacks.
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{primary: string, secondary: string, haloStrength: float}
     * @date 2026-05-09
     * @author Stephane H.
     */
    private function resolveBackgroundFromPayload(array $payload): array
    {
        $style = $this->resolveAtmosphereStyleKeyFromPayload($payload);

        if ($style !== AboutSectionAtmospherePresetRegistry::STYLE_CUSTOM
            && in_array($style, AboutSectionAtmospherePresetRegistry::PRESET_STYLES, true)) {
            $preset = $this->aboutSectionAtmospherePresetRegistry->getPresetDefinition($style);

            return [
                'primary' => $preset['primary'],
                'secondary' => $preset['secondary'],
                'haloStrength' => $preset['haloStrength'],
                'atmosphereStyle' => $style,
                'sectionBackgroundOverride' => $preset['sectionBackgroundOverride'],
            ];
        }

        $customOverride = is_string($payload['aboutSectionAtmosphereCssSanitized'] ?? null)
            ? trim((string) $payload['aboutSectionAtmosphereCssSanitized'])
            : '';

        return [
            'primary' => $this->sanitizeHexColor($payload['aboutBackgroundPrimary'] ?? null, self::DEFAULT_BACKGROUND_PRIMARY_HEX),
            'secondary' => $this->sanitizeHexColor($payload['aboutBackgroundSecondary'] ?? null, self::DEFAULT_BACKGROUND_SECONDARY_HEX),
            'haloStrength' => $this->sanitizeDecimalRange(
                $payload['aboutHaloStrength'] ?? null,
                self::DEFAULT_HALO_STRENGTH,
                0.0,
                1.0
            ),
            'atmosphereStyle' => $style,
            'sectionBackgroundOverride' => $customOverride,
        ];
    }

    /**
     * @brief Resolve persisted atmosphere style key with legacy fallback to style_1.
     *
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return string Allowed style key.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function resolveAtmosphereStyleKeyFromPayload(array $payload): string
    {
        $raw = $payload['aboutSectionAtmosphereStyle'] ?? null;
        if (is_string($raw) && $this->aboutSectionAtmospherePresetRegistry->isValidStyle($raw)) {
            return $raw;
        }

        return AboutSectionAtmospherePresetRegistry::DEFAULT_STYLE;
    }

    /**
     * @brief Sanitize custom atmosphere declaration block from admin textarea.
     *
     * @param string|null $rawCss Raw CSS declarations without outer braces.
     * @return string Sanitized block safe for `.cv-custom-section--about`.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function sanitizeAtmosphereCssForStorage(?string $rawCss): string
    {
        return $this->cssSanitizer->sanitizeDeclarationBlock($rawCss);
    }

    /**
     * @brief Resolve background decoration style and per-style settings from profile JSON.
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{style: AboutBackgroundDecoration, dotGrid: array{opacity: float, gridSizePx: int, dotSizePx: int, maskXPercent: float, maskYPercent: float}, hexZoomMesh: array{intensity: float}, diagonalHatch: array{opacity: float, angleDeg: float, spacingPx: int, lineWidthPx: float, maskXPercent: float, maskYPercent: float}, ambientParticles: array{intensity: float}, isometricGrid: array{intensity: float}, fineGrid: array{opacity: float, stepPx: int, lineWidthPx: float, maskXPercent: float, maskYPercent: float}, devCodeRain: array{intensity: float, speedFactor: float}}
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveBackgroundDecorationFromPayload(array $payload): array
    {
        $adaptive = $this->resolveAdaptiveDecorFromPayload($payload);

        return [
            'style' => AboutBackgroundDecoration::fromStored($payload['aboutBackgroundDecoration'] ?? null, $payload),
            'dotGrid' => $this->resolveDotGridFromPayload($payload),
            'hexZoomMesh' => ['intensity' => $adaptive['hexIntensity']],
            'diagonalHatch' => $this->resolveDiagonalHatchFromPayload($payload),
            'ambientParticles' => [
                'intensity' => $adaptive['particlesIntensity'],
                'speedFactor' => $adaptive['particlesSpeedFactor'],
            ],
            'isometricGrid' => ['intensity' => $adaptive['isoIntensity']],
            'fineGrid' => $this->resolveFineGridFromPayload($payload),
            'devCodeRain' => [
                'intensity' => $adaptive['codeRainIntensity'],
                'speedFactor' => $adaptive['codeRainSpeedFactor'],
            ],
        ];
    }

    /**
     * @brief Resolve theme-adaptive decoration intensities from profile JSON.
     *
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{hexIntensity: float, particlesIntensity: float, particlesSpeedFactor: float, isoIntensity: float, codeRainIntensity: float, codeRainSpeedFactor: float}
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveAdaptiveDecorFromPayload(array $payload): array
    {
        return [
            'hexIntensity' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorHexIntensity'] ?? null,
                self::DEFAULT_ADAPTIVE_INTENSITY,
                self::MIN_ADAPTIVE_INTENSITY,
                self::MAX_ADAPTIVE_INTENSITY
            ),
            'particlesIntensity' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorParticlesIntensity'] ?? null,
                self::DEFAULT_ADAPTIVE_INTENSITY,
                self::MIN_ADAPTIVE_INTENSITY,
                self::MAX_ADAPTIVE_INTENSITY
            ),
            'particlesSpeedFactor' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorParticlesSpeedFactor'] ?? null,
                self::DEFAULT_PARTICLES_SPEED_FACTOR,
                self::MIN_PARTICLES_SPEED_FACTOR,
                self::MAX_PARTICLES_SPEED_FACTOR
            ),
            'isoIntensity' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorIsoIntensity'] ?? null,
                self::DEFAULT_ADAPTIVE_INTENSITY,
                self::MIN_ADAPTIVE_INTENSITY,
                self::MAX_ADAPTIVE_INTENSITY
            ),
            'codeRainIntensity' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorCodeRainIntensity'] ?? null,
                self::DEFAULT_ADAPTIVE_INTENSITY,
                self::MIN_ADAPTIVE_INTENSITY,
                self::MAX_ADAPTIVE_INTENSITY
            ),
            'codeRainSpeedFactor' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorCodeRainSpeedFactor'] ?? null,
                self::DEFAULT_CODE_RAIN_SPEED_FACTOR,
                self::MIN_CODE_RAIN_SPEED_FACTOR,
                self::MAX_CODE_RAIN_SPEED_FACTOR
            ),
        ];
    }

    /**
     * @brief Resolve dot-grid settings from profile JSON with safe defaults.
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{opacity: float, gridSizePx: int, dotSizePx: int, maskXPercent: float, maskYPercent: float}
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveDotGridFromPayload(array $payload): array
    {
        return [
            'opacity' => $this->sanitizeDecimalRange(
                $payload['aboutDotsOpacity'] ?? null,
                self::DEFAULT_DOTS_OPACITY,
                0.0,
                1.0
            ),
            'gridSizePx' => $this->sanitizeInt(
                $payload['aboutDotsGridSizePx'] ?? null,
                self::DEFAULT_DOTS_GRID_SIZE_PX,
                self::MIN_DOTS_GRID_PX,
                self::MAX_DOTS_GRID_PX
            ),
            'dotSizePx' => $this->sanitizeInt(
                $payload['aboutDotsDotSizePx'] ?? null,
                self::DEFAULT_DOTS_DOT_SIZE_PX,
                self::MIN_DOTS_DOT_SIZE_PX,
                self::MAX_DOTS_DOT_SIZE_PX
            ),
            'maskXPercent' => $this->sanitizePercent(
                $payload['aboutDotsMaskXPercent'] ?? null,
                self::DEFAULT_DOTS_MASK_X_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
            'maskYPercent' => $this->sanitizePercent(
                $payload['aboutDotsMaskYPercent'] ?? null,
                self::DEFAULT_DOTS_MASK_Y_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
        ];
    }

    /**
     * @brief Resolve diagonal hatch background decoration settings from profile JSON.
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{opacity: float, angleDeg: float, spacingPx: int, lineWidthPx: float, maskXPercent: float, maskYPercent: float}
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveDiagonalHatchFromPayload(array $payload): array
    {
        return [
            'opacity' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorHatchOpacity'] ?? null,
                self::DEFAULT_HATCH_OPACITY,
                0.0,
                1.0
            ),
            'angleDeg' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorHatchAngleDeg'] ?? null,
                self::DEFAULT_HATCH_ANGLE_DEG,
                0.0,
                360.0
            ),
            'spacingPx' => $this->sanitizeInt(
                $payload['aboutBgDecorHatchSpacingPx'] ?? null,
                self::DEFAULT_HATCH_SPACING_PX,
                self::MIN_HATCH_SPACING_PX,
                self::MAX_HATCH_SPACING_PX
            ),
            'lineWidthPx' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorHatchLineWidthPx'] ?? null,
                self::DEFAULT_HATCH_LINE_WIDTH_PX,
                self::MIN_HATCH_LINE_WIDTH_PX,
                self::MAX_HATCH_LINE_WIDTH_PX
            ),
            'maskXPercent' => $this->sanitizePercent(
                $payload['aboutBgDecorHatchMaskXPercent'] ?? null,
                self::DEFAULT_HATCH_MASK_X_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
            'maskYPercent' => $this->sanitizePercent(
                $payload['aboutBgDecorHatchMaskYPercent'] ?? null,
                self::DEFAULT_HATCH_MASK_Y_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
        ];
    }

    /**
     * @brief Resolve fine-grid background decoration settings from profile JSON.
     *
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{opacity: float, stepPx: int, lineWidthPx: float, maskXPercent: float, maskYPercent: float}
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveFineGridFromPayload(array $payload): array
    {
        return [
            'opacity' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorFineGridOpacity'] ?? null,
                self::DEFAULT_FINE_GRID_OPACITY,
                0.0,
                1.0
            ),
            'stepPx' => $this->sanitizeInt(
                $payload['aboutBgDecorFineGridStepPx'] ?? null,
                self::DEFAULT_FINE_GRID_STEP_PX,
                self::MIN_FINE_GRID_STEP_PX,
                self::MAX_FINE_GRID_STEP_PX
            ),
            'lineWidthPx' => $this->sanitizeDecimalRange(
                $payload['aboutBgDecorFineGridLineWidthPx'] ?? null,
                self::DEFAULT_FINE_GRID_LINE_WIDTH_PX,
                self::MIN_FINE_GRID_LINE_WIDTH_PX,
                self::MAX_FINE_GRID_LINE_WIDTH_PX
            ),
            'maskXPercent' => $this->sanitizePercent(
                $payload['aboutBgDecorFineGridMaskXPercent'] ?? null,
                self::DEFAULT_FINE_GRID_MASK_X_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
            'maskYPercent' => $this->sanitizePercent(
                $payload['aboutBgDecorFineGridMaskYPercent'] ?? null,
                self::DEFAULT_FINE_GRID_MASK_Y_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
        ];
    }

    /**
     * @brief Build CSS custom-property lines for the active background decoration style.
     * @param array{style: AboutBackgroundDecoration, dotGrid: array<string, mixed>, hexZoomMesh: array<string, mixed>, diagonalHatch: array<string, mixed>, ambientParticles: array<string, mixed>, isometricGrid: array<string, mixed>, fineGrid: array<string, mixed>, devCodeRain: array<string, mixed>} $backgroundDecoration Resolved decoration block.
     * @return string Indented CSS variable declarations for {@code .cv-custom-section--about}.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function buildBackgroundDecorationCssVariableLines(array $backgroundDecoration): string
    {
        $style = $backgroundDecoration['style'] ?? AboutBackgroundDecoration::DotGrid;
        if (!$style instanceof AboutBackgroundDecoration) {
            $style = AboutBackgroundDecoration::DotGrid;
        }

        if ($style === AboutBackgroundDecoration::None) {
            return "        --about-bg-decor-enabled: 0;\n";
        }

        $styleLines = match ($style) {
            AboutBackgroundDecoration::DotGrid => $this->formatDotGridCssVariableLines(
                is_array($backgroundDecoration['dotGrid'] ?? null) ? $backgroundDecoration['dotGrid'] : []
            ),
            AboutBackgroundDecoration::HexZoomMesh => $this->formatHexZoomMeshCssVariableLines(
                is_array($backgroundDecoration['hexZoomMesh'] ?? null) ? $backgroundDecoration['hexZoomMesh'] : []
            ),
            AboutBackgroundDecoration::DiagonalHatch => $this->formatDiagonalHatchCssVariableLines(
                is_array($backgroundDecoration['diagonalHatch'] ?? null) ? $backgroundDecoration['diagonalHatch'] : []
            ),
            AboutBackgroundDecoration::AmbientParticles => $this->formatAmbientParticlesCssVariableLines(
                is_array($backgroundDecoration['ambientParticles'] ?? null) ? $backgroundDecoration['ambientParticles'] : []
            ),
            AboutBackgroundDecoration::IsometricGrid => $this->formatIsometricGridCssVariableLines(
                is_array($backgroundDecoration['isometricGrid'] ?? null) ? $backgroundDecoration['isometricGrid'] : []
            ),
            AboutBackgroundDecoration::FineGrid => $this->formatFineGridCssVariableLines(
                is_array($backgroundDecoration['fineGrid'] ?? null) ? $backgroundDecoration['fineGrid'] : []
            ),
            AboutBackgroundDecoration::DevCodeRain => $this->formatDevCodeRainCssVariableLines(
                is_array($backgroundDecoration['devCodeRain'] ?? null) ? $backgroundDecoration['devCodeRain'] : []
            ),
        };

        return $this->formatAdaptiveThemeBaseCssVariableLines().$styleLines;
    }

    /**
     * @brief Format portrait-frame disk colors derived from section background hues.
     *
     * @param array<string, mixed> $disk Resolved disk settings (opacity and glow strengths only).
     * @return string Indented CSS variable lines for {@code .cv-custom-section--about}.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatPortraitAdaptiveCssVariableLines(array $disk): string
    {
        $borderOpacity = $this->sanitizeDecimalRange(
            $disk['borderOpacity'] ?? null,
            self::DEFAULT_DISK_BORDER_OPACITY,
            0.0,
            1.0
        );
        $glowOuterOpacity = $this->sanitizeDecimalRange(
            $disk['glowOuterOpacity'] ?? null,
            self::DEFAULT_DISK_GLOW_OUTER_OPACITY,
            0.0,
            1.0
        );
        $glowInnerOpacity = $this->sanitizeDecimalRange(
            $disk['glowInnerOpacity'] ?? null,
            self::DEFAULT_DISK_GLOW_INNER_OPACITY,
            0.0,
            1.0
        );
        $borderTransparent = $this->formatCssNumber((1.0 - $borderOpacity) * 100.0);
        $glowOuterTransparent = $this->formatCssNumber((1.0 - $glowOuterOpacity) * 100.0);
        $glowInnerTransparent = $this->formatCssNumber((1.0 - $glowInnerOpacity) * 100.0);

        return <<<CSS
    --about-portrait-inner-rgba: color-mix(in srgb, var(--about-bg-secondary) 42%, #fff 58%);
    --about-portrait-outer-rgba: color-mix(in srgb, var(--about-bg-primary) 28%, var(--about-bg-secondary) 72%);
    --about-portrait-highlight-rgba: color-mix(in srgb, var(--about-portrait-inner-rgba) 50%, #fff 50%);
    --about-disk-color-inner-rgba: var(--about-portrait-inner-rgba);
    --about-disk-color-outer-rgba: var(--about-portrait-outer-rgba);
    --about-disk-border-rgba: color-mix(in srgb, var(--about-portrait-highlight-rgba), transparent {$borderTransparent}%);
    --about-disk-glow-outer-rgba: color-mix(in srgb, var(--about-portrait-inner-rgba), transparent {$glowOuterTransparent}%);
    --about-disk-glow-inner-rgba: color-mix(in srgb, var(--about-portrait-outer-rgba), transparent {$glowInnerTransparent}%);

CSS;
    }

    /**
     * @brief Format theme-adaptive base variables always emitted for enabled decorations.
     *
     * @return string Indented CSS variable lines.
     * @date 2026-05-18
     * @author Stephane H.
     */
    /**
     * @brief Format theme-adaptive base variables always emitted for enabled decorations.
     *
     * @return string Indented CSS variable lines.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatAdaptiveThemeBaseCssVariableLines(): string
    {
        return <<<CSS
        --about-bg-decor-tint-rgba: color-mix(in srgb, var(--about-bg-secondary) 55%, #fff 45%);
        --about-bg-decor-line-rgba: color-mix(
            in srgb,
            color-mix(in srgb, var(--about-bg-decor-tint-rgba) 30%, #fff 70%),
            transparent 12%
        );
        --about-bg-decor-intensity: 1;
        --about-code-speed-factor: 1;
        --about-particles-speed-factor: 1;

CSS;
    }

    /**
     * @brief Format hex-zoom-mesh adaptive intensity variables.
     *
     * @param array<string, mixed> $hexZoomMesh Resolved hex mesh settings.
     * @return string Indented CSS variable lines.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatHexZoomMeshCssVariableLines(array $hexZoomMesh): string
    {
        $intensity = $this->formatCssAlpha((float) ($hexZoomMesh['intensity'] ?? self::DEFAULT_ADAPTIVE_INTENSITY));

        return <<<CSS
        --about-bg-decor-enabled: 1;
        --about-bg-decor-intensity: {$intensity};

CSS;
    }

    /**
     * @brief Format ambient-particles adaptive intensity variables.
     *
     * @param array<string, mixed> $particles Resolved particles settings (`intensity`, `speedFactor`).
     * @return string Indented CSS variable lines.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatAmbientParticlesCssVariableLines(array $particles): string
    {
        $intensity = $this->formatCssAlpha((float) ($particles['intensity'] ?? self::DEFAULT_ADAPTIVE_INTENSITY));
        $speedFactor = $this->formatCssNumber((float) ($particles['speedFactor'] ?? self::DEFAULT_PARTICLES_SPEED_FACTOR));

        return <<<CSS
        --about-bg-decor-enabled: 1;
        --about-bg-decor-intensity: {$intensity};
        --about-particles-speed-factor: {$speedFactor};

CSS;
    }

    /**
     * @brief Format isometric-grid adaptive intensity variables.
     *
     * @param array<string, mixed> $isoGrid Resolved isometric grid settings.
     * @return string Indented CSS variable lines.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatIsometricGridCssVariableLines(array $isoGrid): string
    {
        $intensity = $this->formatCssAlpha((float) ($isoGrid['intensity'] ?? self::DEFAULT_ADAPTIVE_INTENSITY));

        return <<<CSS
        --about-bg-decor-enabled: 1;
        --about-bg-decor-intensity: {$intensity};

CSS;
    }

    /**
     * @brief Format dev-code-rain adaptive intensity and speed variables.
     *
     * @param array<string, mixed> $codeRain Resolved code-rain settings.
     * @return string Indented CSS variable lines.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatDevCodeRainCssVariableLines(array $codeRain): string
    {
        $intensity = $this->formatCssAlpha((float) ($codeRain['intensity'] ?? self::DEFAULT_ADAPTIVE_INTENSITY));
        $speedFactor = $this->formatCssNumber((float) ($codeRain['speedFactor'] ?? self::DEFAULT_CODE_RAIN_SPEED_FACTOR));

        return <<<CSS
        --about-bg-decor-enabled: 1;
        --about-bg-decor-intensity: {$intensity};
        --about-code-speed-factor: {$speedFactor};

CSS;
    }

    /**
     * @brief Format dot-grid CSS variables for dynamic About stylesheet.
     * @param array<string, mixed> $dotGrid Resolved dot-grid settings.
     * @return string Indented CSS variable lines.
     * @date 2026-05-15
     * @author Stephane H.
     */
    private function formatDotGridCssVariableLines(array $dotGrid): string
    {
        $opacity = $this->formatCssAlpha((float) ($dotGrid['opacity'] ?? self::DEFAULT_DOTS_OPACITY));
        $gridSizePx = $this->formatCssInteger((int) ($dotGrid['gridSizePx'] ?? self::DEFAULT_DOTS_GRID_SIZE_PX));
        $dotSizePx = $this->formatCssInteger((int) ($dotGrid['dotSizePx'] ?? self::DEFAULT_DOTS_DOT_SIZE_PX));
        $maskX = $this->formatCssNumber((float) ($dotGrid['maskXPercent'] ?? self::DEFAULT_DOTS_MASK_X_PERCENT));
        $maskY = $this->formatCssNumber((float) ($dotGrid['maskYPercent'] ?? self::DEFAULT_DOTS_MASK_Y_PERCENT));

        return <<<CSS
        --about-bg-decor-enabled: 1;
        --about-bg-decor-dots-opacity: {$opacity};
        --about-bg-decor-dots-size: {$gridSizePx}px;
        --about-bg-decor-dots-dot-size: {$dotSizePx}px;
        --about-bg-decor-dots-mask-x: {$maskX}%;
        --about-bg-decor-dots-mask-y: {$maskY}%;

CSS;
    }

    /**
     * @brief Format diagonal hatch CSS variables for dynamic About stylesheet.
     * @param array<string, mixed> $hatch Resolved hatch settings.
     * @return string Indented CSS variable lines.
     * @date 2026-05-15
     * @author Stephane H.
     */
    private function formatDiagonalHatchCssVariableLines(array $hatch): string
    {
        $opacity = $this->formatCssAlpha((float) ($hatch['opacity'] ?? self::DEFAULT_HATCH_OPACITY));
        $angle = $this->formatCssNumber((float) ($hatch['angleDeg'] ?? self::DEFAULT_HATCH_ANGLE_DEG));
        $spacingPx = $this->formatCssInteger((int) ($hatch['spacingPx'] ?? self::DEFAULT_HATCH_SPACING_PX));
        $lineWidthPx = $this->formatCssNumber((float) ($hatch['lineWidthPx'] ?? self::DEFAULT_HATCH_LINE_WIDTH_PX));
        $maskX = $this->formatCssNumber((float) ($hatch['maskXPercent'] ?? self::DEFAULT_HATCH_MASK_X_PERCENT));
        $maskY = $this->formatCssNumber((float) ($hatch['maskYPercent'] ?? self::DEFAULT_HATCH_MASK_Y_PERCENT));

        return <<<CSS
        --about-bg-decor-enabled: 1;
        --about-bg-decor-hatch-opacity: {$opacity};
        --about-bg-decor-hatch-angle: {$angle}deg;
        --about-bg-decor-hatch-spacing: {$spacingPx}px;
        --about-bg-decor-hatch-line-width: {$lineWidthPx}px;
        --about-bg-decor-hatch-mask-x: {$maskX}%;
        --about-bg-decor-hatch-mask-y: {$maskY}%;

CSS;
    }

    /**
     * @brief Format fine-grid CSS variables for dynamic About stylesheet.
     *
     * @param array<string, mixed> $fineGrid Resolved fine-grid settings.
     * @return string Indented CSS variable lines.
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function formatFineGridCssVariableLines(array $fineGrid): string
    {
        $opacity = $this->formatCssAlpha((float) ($fineGrid['opacity'] ?? self::DEFAULT_FINE_GRID_OPACITY));
        $stepPx = $this->formatCssInteger((int) ($fineGrid['stepPx'] ?? self::DEFAULT_FINE_GRID_STEP_PX));
        $lineWidthPx = $this->formatCssNumber((float) ($fineGrid['lineWidthPx'] ?? self::DEFAULT_FINE_GRID_LINE_WIDTH_PX));
        $maskX = $this->formatCssNumber((float) ($fineGrid['maskXPercent'] ?? self::DEFAULT_FINE_GRID_MASK_X_PERCENT));
        $maskY = $this->formatCssNumber((float) ($fineGrid['maskYPercent'] ?? self::DEFAULT_FINE_GRID_MASK_Y_PERCENT));

        return <<<CSS
        --about-bg-decor-enabled: 1;
        --about-bg-decor-fine-grid-opacity: {$opacity};
        --about-bg-decor-fine-grid-step: {$stepPx}px;
        --about-bg-decor-fine-grid-line-width: {$lineWidthPx}px;
        --about-bg-decor-fine-grid-mask-x: {$maskX}%;
        --about-bg-decor-fine-grid-mask-y: {$maskY}%;

CSS;
    }

    /**
     * @brief Resolve disk settings from payload while preserving legacy ring compatibility.
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{enabled: bool, scale: float, opacity: float, subjectX: float, subjectY: float, thicknessPx: int, borderOpacity: float, glowOuterOpacity: float, glowInnerOpacity: float, glowOuterBlurPx: int, glowInnerBlurPx: int}
     * @date 2026-05-18
     * @author Stephane H.
     */
    private function resolveDiskFromPayload(array $payload): array
    {
        $enabled = self::DEFAULT_DISK_ENABLED;
        $rawEnabled = $payload['aboutDiskEnabled'] ?? null;
        if (is_bool($rawEnabled)) {
            $enabled = $rawEnabled;
        } elseif ($rawEnabled !== null) {
            $enabled = filter_var($rawEnabled, FILTER_VALIDATE_BOOLEAN);
        }

        return [
            'enabled' => $enabled,
            'scale' => $this->sanitizeDecimalRange(
                $payload['aboutDiskScale'] ?? ($payload['aboutProfilePhotoRingScale'] ?? null),
                self::DEFAULT_RING_SCALE,
                self::MIN_RING_SCALE,
                self::MAX_RING_SCALE
            ),
            'opacity' => $this->sanitizeDecimalRange(
                $payload['aboutDiskOpacity'] ?? null,
                self::DEFAULT_DISK_OPACITY,
                0.0,
                1.0
            ),
            'subjectX' => $this->sanitizePercent(
                $payload['aboutDiskSubjectX'] ?? ($payload['aboutProfilePhotoSubjectXPercent'] ?? null),
                self::DEFAULT_SUBJECT_X_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
            'subjectY' => $this->sanitizePercent(
                $payload['aboutDiskSubjectY'] ?? ($payload['aboutProfilePhotoSubjectYPercent'] ?? null),
                self::DEFAULT_SUBJECT_Y_PERCENT,
                self::MIN_SUBJECT_PERCENT,
                self::MAX_SUBJECT_PERCENT
            ),
            'thicknessPx' => $this->sanitizeInt(
                $payload['aboutDiskBorderThicknessPx'] ?? ($payload['aboutProfilePhotoRingThicknessPx'] ?? null),
                self::DEFAULT_RING_THICKNESS_PX,
                self::MIN_RING_THICKNESS_PX,
                self::MAX_RING_THICKNESS_PX
            ),
            'borderOpacity' => $this->sanitizeDecimalRange(
                $payload['aboutDiskBorderOpacity'] ?? null,
                self::DEFAULT_DISK_BORDER_OPACITY,
                0.0,
                1.0
            ),
            'glowOuterOpacity' => $this->sanitizeDecimalRange(
                $payload['aboutDiskGlowOuterOpacity'] ?? null,
                self::DEFAULT_DISK_GLOW_OUTER_OPACITY,
                0.0,
                1.0
            ),
            'glowInnerOpacity' => $this->sanitizeDecimalRange(
                $payload['aboutDiskGlowInnerOpacity'] ?? null,
                self::DEFAULT_DISK_GLOW_INNER_OPACITY,
                0.0,
                1.0
            ),
            'glowOuterBlurPx' => $this->sanitizeInt(
                $payload['aboutDiskGlowOuterBlurPx'] ?? null,
                self::DEFAULT_DISK_GLOW_OUTER_BLUR_PX,
                self::MIN_DISK_GLOW_OUTER_BLUR_PX,
                self::MAX_DISK_GLOW_OUTER_BLUR_PX
            ),
            'glowInnerBlurPx' => $this->sanitizeInt(
                $payload['aboutDiskGlowInnerBlurPx'] ?? null,
                self::DEFAULT_DISK_GLOW_INNER_BLUR_PX,
                self::MIN_DISK_GLOW_INNER_BLUR_PX,
                self::MAX_DISK_GLOW_INNER_BLUR_PX
            ),
        ];
    }

    /**
     * @brief Resolve ring focus and sizing variables from payload with safe clamping.
     * @param array<string, mixed> $payload Decoded profile payload.
     * @return array{subjectX: float, subjectY: float, scale: float, thicknessPx: int}
     * @date 2026-05-09
     * @author Stephane H.
     */
    private function resolveRingFromPayload(array $payload): array
    {
        $disk = $this->resolveDiskFromPayload($payload);

        return [
            'subjectX' => $disk['subjectX'],
            'subjectY' => $disk['subjectY'],
            'scale' => $disk['scale'],
            'thicknessPx' => $disk['thicknessPx'],
        ];
    }

    /**
     * @brief Normalize hex color to #rrggbb or return provided fallback color.
     * @param mixed $raw Raw color string.
     * @return string
     * @date 2026-05-23
     * @author Stephane H.
     */
    private function sanitizeHexColor(mixed $raw, string $fallback = self::DEFAULT_BACKGROUND_PRIMARY_HEX): string
    {
        if (!is_string($raw)) {
            return $fallback;
        }

        $trimmed = strtolower(trim($raw));
        if ($trimmed === '') {
            return $fallback;
        }

        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $trimmed)) {
            return $fallback;
        }

        if (strlen($trimmed) === 4) {
            $r = $trimmed[1];
            $g = $trimmed[2];
            $b = $trimmed[3];

            return '#'.$r.$r.$g.$g.$b.$b;
        }

        return $trimmed;
    }

    /**
     * @brief Format alpha component for CSS rgba with bounded decimals.
     * @param float $opacity Alpha value.
     * @return string
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function formatCssAlpha(float $opacity): string
    {
        if (!is_finite($opacity)) {
            return '0';
        }

        $clamped = max(0.0, min(1.0, $opacity));

        return rtrim(rtrim(number_format($clamped, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * @brief Format signed integer for CSS length tokens.
     * @param int $value Integer value.
     * @return string
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function formatCssInteger(int $value): string
    {
        return (string) $value;
    }

    /**
     * @brief Decode JSON payload as associative array.
     * @param string $json Profile JSON text.
     * @return array<string, mixed>
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function decodeJsonPayload(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @brief Determine whether the stored About profile photo path is a user upload.
     *
     * @param mixed $storedPath Raw `aboutProfilePhotoPath` from CvProfile content JSON.
     * @return bool True when a custom upload path is stored.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function hasUserProfilePhoto(mixed $storedPath): bool
    {
        if (!is_string($storedPath)) {
            return false;
        }

        $normalizedPath = trim($storedPath);
        if ($normalizedPath === '' || str_starts_with($normalizedPath, '/')) {
            return false;
        }

        if (in_array($normalizedPath, self::DEPRECATED_PROFILE_PHOTO_PATHS, true)) {
            return false;
        }

        return str_starts_with($normalizedPath, self::ABOUT_CUSTOM_UPLOAD_ROOT.'/');
    }

    /**
     * @brief Resolve public About profile photo path from stored JSON value.
     *
     * @param mixed $storedPath Raw `aboutProfilePhotoPath` from CvProfile content JSON.
     * @return string Relative path under public/ for Twig `asset()` or admin preview.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function resolveProfilePhotoDisplayPath(mixed $storedPath): string
    {
        if ($this->hasUserProfilePhoto($storedPath)) {
            return trim((string) $storedPath);
        }

        return self::PROFILE_PHOTO_PLACEHOLDER_PATH;
    }

    /**
     * @brief Clamp and coerce percentage value for CSS output.
     * @param mixed $raw Raw submitted value.
     * @param float $default Default when invalid.
     * @param float $min Minimum inclusive.
     * @param float $max Maximum inclusive.
     * @return float
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function sanitizePercent(mixed $raw, float $default, float $min, float $max): float
    {
        if (!is_numeric($raw)) {
            return $default;
        }

        $value = (float) $raw;
        if (!is_finite($value)) {
            return $default;
        }

        return max($min, min($max, $value));
    }

    /**
     * @brief Clamp and coerce integer value for JSON-backed CSS properties.
     * @param mixed $raw Raw submitted value.
     * @param int $default Default when invalid.
     * @param int $min Minimum inclusive.
     * @param int $max Maximum inclusive.
     * @return int
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function sanitizeInt(mixed $raw, int $default, int $min, int $max): int
    {
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false) {
            return $default;
        }

        return max($min, min($max, $value));
    }

    /**
     * @brief Clamp and coerce finite decimal values for CSS variables.
     * @param mixed $raw Raw submitted value.
     * @param float $default Default when invalid.
     * @param float $min Minimum inclusive.
     * @param float $max Maximum inclusive.
     * @return float
     * @date 2026-05-09
     * @author Stephane H.
     */
    private function sanitizeDecimalRange(mixed $raw, float $default, float $min, float $max): float
    {
        if (!is_numeric($raw)) {
            return $default;
        }

        $value = (float) $raw;
        if (!is_finite($value)) {
            return $default;
        }

        return max($min, min($max, $value));
    }

    /**
     * @brief Format finite float for safe CSS insertion.
     * @param float $value Numeric value.
     * @return string
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function formatCssNumber(float $value): string
    {
        if (!is_finite($value)) {
            return '0';
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
