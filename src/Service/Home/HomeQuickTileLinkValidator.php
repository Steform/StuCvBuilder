<?php

declare(strict_types=1);

namespace App\Service\Home;

/**
 * @brief Validate custom home quick tile link targets.
 */
final class HomeQuickTileLinkValidator
{
    private const FORBIDDEN_SCHEMES = [
        'javascript:',
        'data:',
        'vbscript:',
        'file:',
    ];

    /**
     * @brief Validate and normalize a tile link URL.
     *
     * @param string $rawUrl Raw user input.
     * @return string Normalized href safe for rendering.
     * @throws \InvalidArgumentException When the link is rejected.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function validateAndNormalize(string $rawUrl): string
    {
        $url = trim($rawUrl);
        if ($url === '') {
            throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.invalid_link');
        }

        $lower = strtolower($url);
        foreach (self::FORBIDDEN_SCHEMES as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.invalid_link');
            }
        }

        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) {
                throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.invalid_link');
            }

            return $url;
        }

        if (str_starts_with($lower, 'https://')) {
            return $url;
        }

        throw new \InvalidArgumentException('dashboard.customization_quick_tiles.flash.invalid_link');
    }

    /**
     * @brief Guess whether a normalized link should open in a new tab by default.
     *
     * @param string $normalizedUrl Validated href from {@see validateAndNormalize()}.
     * @return bool True for external https links.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function suggestsOpenInNewTab(string $normalizedUrl): bool
    {
        return str_starts_with(strtolower($normalizedUrl), 'https://');
    }
}
