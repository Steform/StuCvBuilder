<?php

declare(strict_types=1);

namespace App\Service\Home;

/**
 * @brief Read-only view model for a public home quick tile.
 */
final readonly class HomeQuickTileResolvedView
{
    /**
     * @brief Build resolved tile view for Twig rendering.
     * @param int $id Tile primary key.
     * @param string $href Validated link target.
     * @param string $iconRelativePath Icon path relative to public/.
     * @param string $label Localized visible label.
     * @param string $alt Accessible image alt text.
     * @param bool $openInNewTab Whether to open in a new tab.
     * @param bool $enabled Whether the tile is visible on the public home strip.
     * @param array<string, string> $labelsByLocale Localized labels keyed by locale code.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function __construct(
        public int $id,
        public string $href,
        public string $iconRelativePath,
        public string $label,
        public string $alt,
        public bool $openInNewTab,
        public bool $enabled = true,
        public array $labelsByLocale = [],
    ) {
    }
}
