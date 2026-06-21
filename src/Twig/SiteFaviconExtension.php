<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Home\HomeCustomizationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @brief Expose resolved site favicon path and MIME type to Twig layouts.
 */
final class SiteFaviconExtension extends AbstractExtension
{
    /**
     * @brief Construct extension with home customization resolver.
     *
     * @param HomeCustomizationService $homeCustomizationService Home customization service.
     * @return void
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function __construct(
        private readonly HomeCustomizationService $homeCustomizationService,
    ) {
    }

    /**
     * @brief Register Twig functions.
     *
     * @param void No input parameter.
     * @return TwigFunction[]
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_favicon_href', [$this, 'siteFaviconHref']),
            new TwigFunction('site_favicon_type', [$this, 'siteFaviconType']),
        ];
    }

    /**
     * @brief Resolve relative public path for the active site favicon.
     *
     * @param void No input parameter.
     * @return string Path segment for Symfony asset() helper.
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function siteFaviconHref(): string
    {
        return $this->homeCustomizationService->resolveSiteFaviconRelativePath();
    }

    /**
     * @brief Resolve MIME type for the active site favicon.
     *
     * @param void No input parameter.
     * @return string MIME type for link rel icon.
     * @date 2026-05-18
     * @author Stephane H.
     */
    public function siteFaviconType(): string
    {
        return $this->homeCustomizationService->resolveSiteFaviconMimeType();
    }
}
