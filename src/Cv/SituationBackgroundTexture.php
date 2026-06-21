<?php

declare(strict_types=1);

namespace App\Cv;

/**
 * @brief Allowed Situation section background textures stored as {@see CvProfile} JSON `situationBackgroundTexture`.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
enum SituationBackgroundTexture: string
{
    case Texture1 = 'texture_1';
    case Texture2 = 'texture_2';
    case Texture3 = 'texture_3';
    case Texture4 = 'texture_4';
    case Texture5 = 'texture_5';
    case Texture6 = 'texture_6';

    /**
     * @brief Map stored JSON value to enum with safe default.
     *
     * @param mixed $raw Raw `situationBackgroundTexture` value.
     * @return self Resolved texture case.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function fromStored(mixed $raw): self
    {
        if (is_string($raw) && $raw !== '') {
            $resolved = self::tryFrom($raw);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return self::Texture1;
    }

    /**
     * @brief Public asset path relative to the web root (no leading slash).
     *
     * @return string Path such as `images/cv/textures/texture1.webp`.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function relativeAssetPath(): string
    {
        return match ($this) {
            self::Texture1 => 'images/cv/textures/texture1.webp',
            self::Texture2 => 'images/cv/textures/texture2.webp',
            self::Texture3 => 'images/cv/textures/texture3.webp',
            self::Texture4 => 'images/cv/textures/texture4.webp',
            self::Texture5 => 'images/cv/textures/texture5.webp',
            self::Texture6 => 'images/cv/textures/texture6.webp',
        };
    }

    /**
     * @brief Stored string values safe for public BEM class suffixes.
     *
     * @return list<string>
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function storedValues(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }

    /**
     * @brief Cases exposed in admin texture picker.
     *
     * @return list<self>
     * @date 2026-05-20
     * @author Stephane H.
     */
    public static function casesForAdmin(): array
    {
        return self::cases();
    }

    /**
     * @brief Native pixel size of the texture tile for seamless CSS repetition.
     *
     * @return array{widthPx: int, heightPx: int} Width and height in CSS pixels.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function textureTileSizePx(): array
    {
        $absolutePath = dirname(__DIR__, 2).'/public/'.$this->relativeAssetPath();
        if (!is_file($absolutePath)) {
            return ['widthPx' => 400, 'heightPx' => 400];
        }

        $size = @getimagesize($absolutePath);
        if ($size === false) {
            return ['widthPx' => 400, 'heightPx' => 400];
        }

        return [
            'widthPx' => max(1, (int) $size[0]),
            'heightPx' => max(1, (int) $size[1]),
        ];
    }

    /**
     * @brief @deprecated Use {@see textureTileSizePx()}.
     *
     * @return array{widthPx: int, heightPx: int} Width and height in CSS pixels.
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function maskTileSizePx(): array
    {
        return $this->textureTileSizePx();
    }
}
