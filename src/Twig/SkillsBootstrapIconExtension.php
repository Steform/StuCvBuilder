<?php

declare(strict_types=1);

namespace App\Twig;

use App\Cv\BootstrapIconsManifest;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @brief Twig helpers for the CV skills Bootstrap icon browser sub-modal.
 *
 * @date 2026-06-10
 * @author Stephane H.
 */
final class SkillsBootstrapIconExtension extends AbstractExtension
{
    /**
     * @brief Register Twig functions exposed by this extension.
     *
     * @return list<TwigFunction>
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cv_skills_bootstrap_icon_default', [$this, 'getDefaultIcon']),
            new TwigFunction('cv_skills_bootstrap_icon_manifest_url', [$this, 'getManifestUrl']),
        ];
    }

    /**
     * @brief Return the default Bootstrap icon class for new skill items.
     *
     * @return string
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function getDefaultIcon(): string
    {
        return BootstrapIconsManifest::DEFAULT_ICON;
    }

    /**
     * @brief Return the Bootstrap Icons JSON manifest URL used by the admin browser.
     *
     * @return string
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function getManifestUrl(): string
    {
        return BootstrapIconsManifest::MANIFEST_CDN_URL;
    }
}
