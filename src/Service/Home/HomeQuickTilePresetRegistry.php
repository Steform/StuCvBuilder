<?php

declare(strict_types=1);

namespace App\Service\Home;

use InvalidArgumentException;

/**
 * @brief Static CSS declaration presets for unified home quick tiles.
 */
final class HomeQuickTilePresetRegistry
{
    public const STYLE_CUSTOM = 'custom';

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
    ];

    /**
     * @var list<string>
     */
    public const ALLOWED_STYLES = [
        ...self::PRESET_STYLES,
        self::STYLE_CUSTOM,
    ];

    /**
     * @brief Check whether a style key is allowed for persistence.
     *
     * @param string $style Style key from admin form.
     * @return bool True when style is a preset or custom mode.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function isValidStyle(string $style): bool
    {
        return in_array($style, self::ALLOWED_STYLES, true);
    }

    /**
     * @brief Return sanitized CSS declarations for a preset style key.
     *
     * @param string $style Preset key style_1 through style_14.
     * @return string CSS declaration block without outer braces.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function getPresetCss(string $style): string
    {
        return match ($style) {
            'style_1' => $this->style1Current(),
            'style_2' => $this->style2BlueCyan(),
            'style_3' => $this->style3GreenEmerald(),
            'style_4' => $this->style4DarkSolid(),
            'style_5' => $this->style5Glass(),
            'style_6' => $this->style6Outline(),
            'style_7' => $this->style7LightCard(),
            'style_8' => $this->style8PinkViolet(),
            'style_9' => $this->style9Minimal(),
            'style_10' => $this->style10NeonGlow(),
            'style_11' => $this->style11Neomorphism(),
            'style_12' => $this->style12Claymorphism(),
            'style_13' => $this->style13PixelArt(),
            'style_14' => $this->style14MaterialDesign(),
            default => throw new InvalidArgumentException('Unknown quick tile preset: '.$style),
        };
    }

    /**
     * @brief Style 1 — classic violet / orange gradient (original home landing look).
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style1Current(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-image: linear-gradient(to bottom right, rgba(172, 0, 255, 0.3), rgba(255, 94, 0, 0.6));
color: #fff;
border-radius: 10%;
border: none;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
CSS;
    }

    /**
     * @brief Style 2 — cool blue / cyan gradient.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style2BlueCyan(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-image: linear-gradient(135deg, rgba(14, 165, 233, 0.85), rgba(6, 182, 212, 0.55));
color: #fff;
border-radius: 12%;
border: none;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0 0.5rem 1.5rem rgba(14, 165, 233, 0.35);
CSS;
    }

    /**
     * @brief Style 3 — fresh green / emerald gradient.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style3GreenEmerald(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-image: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.6));
color: #fff;
border-radius: 12%;
border: none;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0 0.4rem 1.2rem rgba(16, 185, 129, 0.3);
CSS;
    }

    /**
     * @brief Style 4 — dark solid slate panel.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style4DarkSolid(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-image: none;
background-color: #1e293b;
color: #f8fafc;
border-radius: 1rem;
border: 1px solid rgba(148, 163, 184, 0.35);
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.45);
CSS;
    }

    /**
     * @brief Style 5 — glassmorphism panel.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style5Glass(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background: rgba(255, 255, 255, 0.12);
color: #fff;
border-radius: 1rem;
border: 1px solid rgba(255, 255, 255, 0.35);
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
backdrop-filter: blur(12px);
-webkit-backdrop-filter: blur(12px);
box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);
CSS;
    }

    /**
     * @brief Style 6 — transparent outline card.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style6Outline(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background: transparent;
color: #fff;
border-radius: 1rem;
border: 2px solid rgba(255, 255, 255, 0.9);
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
CSS;
    }

    /**
     * @brief Style 7 — light flat card.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style7LightCard(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background: rgba(248, 250, 252, 0.96);
color: #0f172a;
border-radius: 1rem;
border: 1px solid rgba(226, 232, 240, 0.9);
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.15);
CSS;
    }

    /**
     * @brief Style 8 — warm pink / violet gradient.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style8PinkViolet(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-image: linear-gradient(135deg, rgba(236, 72, 153, 0.85), rgba(139, 92, 246, 0.75));
color: #fff;
border-radius: 12%;
border: none;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0 0.45rem 1.4rem rgba(139, 92, 246, 0.35);
CSS;
    }

    /**
     * @brief Style 9 — minimal tile (same outer spacing as other presets; flex layout keeps label inside).
     * @return string
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function style9Minimal(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background: rgba(255, 255, 255, 0.08);
color: #fff;
border-radius: 1rem;
border: 1px solid rgba(255, 255, 255, 0.2);
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
CSS;
    }

    /**
     * @brief Style 10 — dark neon glow panel.
     * @return string
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function style10NeonGlow(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background: rgba(15, 23, 42, 0.92);
color: #e0f2fe;
border-radius: 1rem;
border: 1px solid rgba(34, 211, 238, 0.55);
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0 0 1.2rem rgba(168, 85, 247, 0.55), 0 0 2rem rgba(34, 211, 238, 0.35);
CSS;
    }

    /**
     * @brief Style 11 — soft neomorphism on dark landing background.
     * @return string
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function style11Neomorphism(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-color: #151a24;
color: #e8edf5;
border-radius: 1.25rem;
border: none;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0.45rem 0.45rem 0.9rem rgba(0, 0, 0, 0.55), -0.25rem -0.25rem 0.75rem rgba(255, 255, 255, 0.07);
CSS;
    }

    /**
     * @brief Style 12 — claymorphism with soft pastel volume.
     * @return string
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function style12Claymorphism(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-image: linear-gradient(145deg, rgba(253, 186, 116, 0.92), rgba(251, 113, 133, 0.88));
color: #ffffff;
border-radius: 1.75rem;
border: none;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0.75rem 0.9rem 1.5rem rgba(109, 40, 217, 0.35), inset 0.15rem 0.15rem 0.4rem rgba(255, 255, 255, 0.4);
CSS;
    }

    /**
     * @brief Style 13 — arcade pixel-art panel (beveled frame, stepped shadow, retro HUD).
     * @return string
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function style13PixelArt(): string
    {
        return <<<'CSS'
font-family: Courier New, monospace;
font-weight: 700;
font-size: 12px;
text-transform: uppercase;
letter-spacing: 0.12em;
background-color: #1a1a40;
background-image: linear-gradient(180deg, rgba(255, 255, 255, 0.14) 0%, transparent 12%, transparent 88%, rgba(0, 0, 0, 0.28) 100%), linear-gradient(180deg, #7678ed 0%, #3d348b 52%, #1a1a40 100%);
color: #c9f70a;
border-radius: 0;
border-top: 4px solid #e8ff5a;
border-left: 4px solid #e8ff5a;
border-right: 4px solid #2d3142;
border-bottom: 6px solid #2d3142;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0.25rem 0.25rem 0 #000000, 0.5rem 0.5rem 0 #2d3142, 0.75rem 0.75rem 0 #0d0d1a, inset 0 0 0 2px rgba(247, 255, 247, 0.12);
text-shadow: 0.125rem 0.125rem 0 #000000, 0.25rem 0.25rem 0 #2d3142;
CSS;
    }

    /**
     * @brief Style 14 — Material Design elevation (primary surface).
     * @return string
     * @date 2026-05-16
     * @author Stephane H.
     */
    private function style14MaterialDesign(): string
    {
        return <<<'CSS'
font: bold 13px Arial, sans-serif;
background-color: #1e88e5;
color: #ffffff;
border-radius: 0.5rem;
border: none;
text-align: center;
height: 250px;
padding-top: 1vh;
margin-bottom: 30px;
box-shadow: 0 0.125rem 0.375rem rgba(0, 0, 0, 0.2), 0 0.375rem 0.75rem rgba(30, 136, 229, 0.35);
CSS;
    }
}
