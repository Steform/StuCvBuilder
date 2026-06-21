<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Service\Security\CssSanitizerService;
use InvalidArgumentException;

/**
 * @brief Preset definitions for About section atmosphere (background + halo on `.cv-custom-section--about`).
 */
final class AboutSectionAtmospherePresetRegistry
{
    public const STYLE_CUSTOM = 'custom';

    public const DEFAULT_STYLE = 'style_11';

    /**
     * @var list<string>
     */
    public const PRESET_STYLES = [
        'style_1',
        'style_2',
        'style_3',
        'style_4',
        'style_5',
        'style_6',
        'style_7',
        'style_8',
        'style_9',
        'style_10',
        'style_11',
        'style_12',
        'style_13',
        'style_14',
        'style_15',
    ];

    /**
     * @var list<string>
     */
    public const ALLOWED_STYLES = [
        ...self::PRESET_STYLES,
        self::STYLE_CUSTOM,
    ];

    public function __construct(
        private readonly CssSanitizerService $cssSanitizer,
    ) {
    }

    /**
     * @brief Check whether a style key is allowed for persistence.
     *
     * @param string $style Style key from admin form.
     * @return bool True when style is a preset or custom mode.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function isValidStyle(string $style): bool
    {
        return in_array($style, self::ALLOWED_STYLES, true);
    }

    /**
     * @brief Return resolved preset fragment for atmosphere (colors, halo, optional section background override).
     *
     * @param string $style Preset key style_1 through style_15.
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function getPresetDefinition(string $style): array
    {
        $raw = match ($style) {
            'style_1' => $this->style1Classic(),
            'style_2' => $this->style2StudioBlack(),
            'style_3' => $this->style3Spotlight(),
            'style_4' => $this->style4Sunset(),
            'style_5' => $this->style5Slate(),
            'style_6' => $this->style6Glass(),
            'style_7' => $this->style7ForestTeal(),
            'style_8' => $this->style8VioletNebula(),
            'style_9' => $this->style9Minimal(),
            'style_10' => $this->style10Cinema(),
            'style_11' => $this->style11DeepBlue(),
            'style_12' => $this->style12ColdFlat(),
            'style_13' => $this->style13Nebula(),
            'style_14' => $this->style14Horizon(),
            'style_15' => $this->style15EditorialSplit(),
            default => throw new InvalidArgumentException('Unknown about atmosphere preset: '.$style),
        };

        return [
            'primary' => $raw['primary'],
            'secondary' => $raw['secondary'],
            'haloStrength' => $raw['haloStrength'],
            'sectionBackgroundOverride' => $this->sanitizeOverride($raw['sectionBackgroundOverride'] ?? ''),
        ];
    }

    /**
     * @brief Sanitize optional background override declarations.
     *
     * @param string $rawCss Raw CSS declaration block without braces.
     * @return string Sanitized block or empty string.
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function sanitizeOverride(string $rawCss): string
    {
        if (trim($rawCss) === '') {
            return '';
        }

        return $this->cssSanitizer->sanitizeDeclarationBlock($rawCss);
    }

    /**
     * @param string $primary Primary hex color.
     * @param string $secondary Secondary hex color.
     * @param float $haloStrength Halo strength 0–1.
     * @param string $sectionBackgroundOverride Optional background declarations.
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function definition(
        string $primary,
        string $secondary,
        float $haloStrength,
        string $sectionBackgroundOverride = '',
    ): array {
        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'haloStrength' => $haloStrength,
            'sectionBackgroundOverride' => $sectionBackgroundOverride,
        ];
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style1Classic(): array
    {
        return $this->definition('#010a22', '#03215a', 0.65);
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style2StudioBlack(): array
    {
        return $this->definition(
            '#050508',
            '#0a0a0f',
            0.0,
            <<<'CSS'
background: #08080c;
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style3Spotlight(): array
    {
        return $this->definition(
            '#030712',
            '#0f172a',
            0.9,
            <<<'CSS'
background-color: #030712;
background-image:
    radial-gradient(42rem 42rem at 70% 58%, rgba(120, 190, 255, 0.55) 0%, transparent 68%),
    radial-gradient(18rem 16rem at 68% 62%, rgba(200, 230, 255, 0.35) 0%, transparent 72%),
    linear-gradient(112deg, #030712 0%, #0f172a 55%, #020617 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style4Sunset(): array
    {
        return $this->definition(
            '#1a0a12',
            '#4a1d2e',
            0.35,
            <<<'CSS'
background-color: #1a0a12;
background-image:
    radial-gradient(36rem 30rem at 72% 55%, rgba(251, 146, 60, 0.28) 0%, transparent 70%),
    linear-gradient(135deg, #2d1218 0%, #6b2d3a 45%, #1a0a12 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style5Slate(): array
    {
        return $this->definition(
            '#1e293b',
            '#334155',
            0.0,
            <<<'CSS'
background: linear-gradient(160deg, #334155 0%, #1e293b 48%, #0f172a 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style6Glass(): array
    {
        return $this->definition(
            '#0f172a',
            '#1e293b',
            0.15,
            <<<'CSS'
background-color: #0b1220;
background-image:
    radial-gradient(50rem 40rem at 30% 20%, rgba(255, 255, 255, 0.08) 0%, transparent 55%),
    linear-gradient(145deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.98) 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style7ForestTeal(): array
    {
        return $this->definition(
            '#042f2e',
            '#0d4a44',
            0.5,
            <<<'CSS'
background-color: #042f2e;
background-image:
    radial-gradient(32rem 28rem at 68% 60%, rgba(45, 212, 191, 0.22) 0%, transparent 70%),
    linear-gradient(118deg, #022c22 0%, #0f766e 42%, #042f2e 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style8VioletNebula(): array
    {
        return $this->definition(
            '#1e1033',
            '#4c1d95',
            0.75,
            <<<'CSS'
background-color: #12081f;
background-image:
    radial-gradient(38rem 34rem at 75% 52%, rgba(168, 85, 247, 0.45) 0%, transparent 68%),
    radial-gradient(28rem 24rem at 40% 70%, rgba(59, 130, 246, 0.25) 0%, transparent 72%),
    linear-gradient(125deg, #1e1033 0%, #312e81 50%, #0f0a1a 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style9Minimal(): array
    {
        return $this->definition(
            '#0f172a',
            '#1e3a5f',
            0.0,
            <<<'CSS'
background: linear-gradient(180deg, #0f172a 0%, #1e3a5f 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style10Cinema(): array
    {
        return $this->definition(
            '#030303',
            '#111111',
            0.2,
            <<<'CSS'
background-color: #030303;
background-image:
    radial-gradient(48rem 40rem at 65% 58%, rgba(80, 80, 90, 0.18) 0%, transparent 72%),
    linear-gradient(180deg, #0a0a0a 0%, #030303 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style11DeepBlue(): array
    {
        return $this->definition('#010a22', '#03215a', 0.55);
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style12ColdFlat(): array
    {
        return $this->definition(
            '#0c1929',
            '#152238',
            0.0,
            <<<'CSS'
background: linear-gradient(165deg, #152238 0%, #0c1929 60%, #070d16 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style13Nebula(): array
    {
        return $this->definition(
            '#0f0a1a',
            '#1e1b4b',
            0.7,
            <<<'CSS'
background-color: #0a0612;
background-image:
    radial-gradient(44rem 40rem at 20% 30%, rgba(34, 211, 238, 0.2) 0%, transparent 55%),
    radial-gradient(40rem 36rem at 78% 62%, rgba(236, 72, 153, 0.28) 0%, transparent 58%),
    radial-gradient(30rem 26rem at 50% 80%, rgba(99, 102, 241, 0.35) 0%, transparent 65%),
    linear-gradient(140deg, #0f0a1a 0%, #1e1b4b 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style14Horizon(): array
    {
        return $this->definition(
            '#0c1445',
            '#020617',
            0.4,
            <<<'CSS'
background-color: #020617;
background-image:
    radial-gradient(50rem 24rem at 50% 100%, rgba(56, 189, 248, 0.15) 0%, transparent 70%),
    linear-gradient(180deg, #1e3a8a 0%, #0f172a 38%, #020617 100%);
CSS
        );
    }

    /**
     * @return array{primary: string, secondary: string, haloStrength: float, sectionBackgroundOverride: string}
     */
    private function style15EditorialSplit(): array
    {
        return $this->definition(
            '#f8fafc',
            '#0f172a',
            0.0,
            <<<'CSS'
background-color: #0f172a;
background-image: linear-gradient(108deg, #f8fafc 0%, #f8fafc 42%, #0f172a 42%, #0f172a 100%);
CSS
        );
    }
}
