<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Cv\CvSiteDocumentTitleService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @brief Expose CV-backed site document title prefix to Twig layouts.
 */
final class CvSiteTitleExtension extends AbstractExtension
{
    public function __construct(
        private readonly CvSiteDocumentTitleService $cvSiteDocumentTitleService,
    ) {
    }

    /**
     * @brief Register Twig functions.
     *
     * @return TwigFunction[]
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cv_site_title_prefix', [$this, 'siteTitlePrefix']),
        ];
    }

    /**
     * @brief Resolve document title prefix from `[[cv.display_name]]` data.
     *
     * @param string|null $locale Viewer locale code; defaults to French when null.
     * @return string Plain-text owner name for the title segment before `|`.
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function siteTitlePrefix(?string $locale = null): string
    {
        $normalizedLocale = trim((string) ($locale ?? 'fr'));
        if ($normalizedLocale === '') {
            $normalizedLocale = 'fr';
        }

        return $this->cvSiteDocumentTitleService->resolveOwnerPrefix($normalizedLocale);
    }
}
