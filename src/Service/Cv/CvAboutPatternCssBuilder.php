<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\CvPencilDecorationContract;

/**
 * @brief Builds dynamic CSS custom properties for the About inline SVG pattern tones.
 *
 * @date 2026-05-27
 * @author Stephane H.
 */
final class CvAboutPatternCssBuilder
{
    /**
     * @brief Emit pattern and accent variables on public shells including Situation.
     *
     * @param array{
     *     baseColor: string,
     *     toneMixPercent: array{left: array<string, int>, right: array<string, int>},
     *     surfaceMixPercent?: int,
     *     darkSurfaceDarkenPercent?: int
     * } $pattern Normalized pattern customization.
     * @param array<string, mixed> $profilePayload Optional CvProfile payload for pencil tone variables.
     * @param string|null $accentColor Optional site accent color for buttons and pencil decoration.
     * @return string Safe CSS declarations.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function buildCss(array $pattern, array $profilePayload = [], ?string $accentColor = null): string
    {
        $patternBaseColor = AboutSectionPatternCustomizationContract::sanitizeHexColor($pattern['baseColor'] ?? null)
            ?? AboutSectionPatternCustomizationContract::DEFAULT_BASE_HEX;
        $accent = AboutSectionPatternCustomizationContract::sanitizeHexColor($accentColor)
            ?? $patternBaseColor;
        $mixBySide = AboutSectionPatternCustomizationContract::normalizeToneMixPercentBySideMap(
            $pattern['toneMixPercent'] ?? null
        );
        $leftMix = $mixBySide[AboutSectionPatternCustomizationContract::TONE_SIDE_LEFT];
        $rightMix = $mixBySide[AboutSectionPatternCustomizationContract::TONE_SIDE_RIGHT];
        $surfaceMix = AboutSectionPatternCustomizationContract::normalizeSurfaceMixPercent(
            $pattern['surfaceMixPercent'] ?? null,
            $leftMix
        );
        $darkSurfaceDarken = AboutSectionPatternCustomizationContract::normalizeDarkSurfaceDarkenPercent(
            $pattern['darkSurfaceDarkenPercent'] ?? null
        );
        $pencilDecoration = CvPencilDecorationContract::fromPayload($profilePayload);
        $lightToneMix = $pencilDecoration[CvPencilDecorationContract::FIELD_LIGHT_TONE_MIX_PERCENT];
        $darkToneMix = $pencilDecoration[CvPencilDecorationContract::FIELD_DARK_TONE_MIX_PERCENT];

        return <<<CSS
.cv-public-page,
.cv-about,
.cv-skills,
.cv-custom-section--situation {
    --cv-about-pattern-base: {$patternBaseColor};
    --cv-about-accent: {$accent};
    --cv-about-pattern-surface-mix: {$surfaceMix}%;
    --cv-about-surface-darken-mix: {$darkSurfaceDarken}%;
}

.cv-about__pattern--left {
    --cv-about-pattern-mix-1: {$leftMix[AboutSectionPatternCustomizationContract::TONE_1]}%;
    --cv-about-pattern-mix-2: {$leftMix[AboutSectionPatternCustomizationContract::TONE_2]}%;
    --cv-about-pattern-mix-3: {$leftMix[AboutSectionPatternCustomizationContract::TONE_3]}%;
    --cv-about-pattern-mix-4: {$leftMix[AboutSectionPatternCustomizationContract::TONE_4]}%;
}

.cv-about__pattern--right {
    --cv-about-pattern-mix-1: {$rightMix[AboutSectionPatternCustomizationContract::TONE_1]}%;
    --cv-about-pattern-mix-2: {$rightMix[AboutSectionPatternCustomizationContract::TONE_2]}%;
    --cv-about-pattern-mix-3: {$rightMix[AboutSectionPatternCustomizationContract::TONE_3]}%;
    --cv-about-pattern-mix-4: {$rightMix[AboutSectionPatternCustomizationContract::TONE_4]}%;
}

.cv-pencil-decor {
    --cv-pencil-tone-light: color-mix(
        in oklch,
        {$accent} calc(100% - {$lightToneMix}%),
        white {$lightToneMix}%
    );
    --cv-pencil-tone-dark: color-mix(
        in oklch,
        {$accent} calc(100% - {$darkToneMix}%),
        white {$darkToneMix}%
    );
}
CSS;
    }
}
