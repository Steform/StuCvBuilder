<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Cv\AboutSectionPatternCustomizationContract;
use App\Cv\SiteColorsContract;
use App\Repository\CvProfileRepository;
use App\Service\Home\HomeCustomizationService;

/**
 * @brief Resolve site-wide accent colors with legacy CvProfile fallback.
 *
 * @date 2026-05-31
 * @author Stephane H.
 */
class SiteColorsResolver
{
    /**
     * @brief Build site colors resolver.
     *
     * @param HomeCustomizationService $homeCustomizationService Home customization singleton access.
     * @param CvProfileRepository $cvProfileRepository Latest CV profile repository.
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function __construct(
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly CvProfileRepository $cvProfileRepository,
    ) {
    }

    /**
     * @brief Return normalized site colors from persisted singleton row.
     *
     * @param void No input parameter.
     * @return array{accent: string|null, cvMenuBackground: string|null}
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function getNormalizedSiteColors(): array
    {
        $customization = $this->homeCustomizationService->getOrCreateSingleton();

        return SiteColorsContract::decodeFromStorage($customization->getSiteColorsJson());
    }

    /**
     * @brief Resolve accent color for admin forms and public rendering.
     *
     * @param void No input parameter.
     * @return string Resolved `#rrggbb` accent color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function resolveAccentColor(): string
    {
        return SiteColorsContract::resolveAccent(
            $this->getNormalizedSiteColors(),
            $this->resolveProfileFallbackAccent()
        );
    }

    /**
     * @brief Resolve public CV sidebar/menu background color for rendering.
     *
     * @param void No input parameter.
     * @return string Resolved `#rrggbb` menu background color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function resolveCvMenuBackground(): string
    {
        return SiteColorsContract::resolveCvMenuBackground($this->getNormalizedSiteColors());
    }

    /**
     * @brief Build cache-busting suffix for public CV sidebar layout CSS.
     *
     * @param void No input parameter.
     * @return string Short fingerprint hash fragment.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function layoutCssCacheSuffix(): string
    {
        return SiteColorsContract::layoutCssCacheSuffix(
            $this->getNormalizedSiteColors(),
            $this->resolveProfileFallbackAccent()
        );
    }

    /**
     * @brief Apply site accent override onto About pattern settings.
     *
     * @param array<string, mixed> $pattern Normalized About pattern map.
     * @return array<string, mixed> Pattern map with resolved accent color.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function applyAccentToPattern(array $pattern): array
    {
        return SiteColorsContract::applyAccentToPattern($pattern, $this->getNormalizedSiteColors());
    }

    /**
     * @brief Build cache-busting suffix for About pattern CSS.
     *
     * @param array<string, mixed> $profilePayload CvProfile JSON payload.
     * @return string Short fingerprint hash fragment.
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function patternCssCacheSuffix(array $profilePayload): string
    {
        return SiteColorsContract::patternCssCacheSuffix($this->getNormalizedSiteColors(), $profilePayload);
    }

    /**
     * @brief Read legacy About base color from latest CV profile payload.
     *
     * @param void No input parameter.
     * @return string|null Profile accent fallback or null when unavailable.
     * @date 2026-05-31
     * @author Stephane H.
     */
    private function resolveProfileFallbackAccent(): ?string
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if ($profile === null) {
            return null;
        }

        $decoded = json_decode($profile->getContentJson(), true);
        if (!is_array($decoded)) {
            return null;
        }

        $pattern = AboutSectionPatternCustomizationContract::fromPayload($decoded);

        return is_string($pattern['baseColor'] ?? null) ? $pattern['baseColor'] : null;
    }
}
