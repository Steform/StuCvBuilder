<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Site\SiteSeoResolverService;
use App\Service\Site\SiteStructuredDataService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @brief Expose configurable SEO meta description and Open Graph guards to Twig layouts.
 */
final class SiteSeoExtension extends AbstractExtension
{
    public function __construct(
        private readonly SiteSeoResolverService $siteSeoResolverService,
        private readonly SiteStructuredDataService $siteStructuredDataService,
    ) {
    }

    /**
     * @brief Register Twig functions.
     *
     * @return TwigFunction[]
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_seo_meta_description', [$this, 'metaDescription']),
            new TwigFunction('site_seo_cv_meta_description', [$this, 'cvMetaDescription']),
            new TwigFunction('site_seo_home_page_title', [$this, 'homePageTitle']),
            new TwigFunction('site_seo_cv_page_title', [$this, 'cvPageTitle']),
            new TwigFunction('site_seo_hreflang_alternates', [$this, 'hreflangAlternates']),
            new TwigFunction('site_seo_open_graph_enabled', [$this, 'openGraphEnabled']),
            new TwigFunction('site_seo_share_image_path', [$this, 'shareImagePath']),
            new TwigFunction('site_seo_canonical_url', [$this, 'canonicalUrl']),
            new TwigFunction('site_seo_twitter_card_type', [$this, 'twitterCardType']),
            new TwigFunction('site_seo_open_graph_locale', [$this, 'openGraphLocale']),
            new TwigFunction('site_seo_open_graph_locale_alternates', [$this, 'openGraphLocaleAlternates']),
            new TwigFunction('site_seo_structured_data_json', [$this, 'structuredDataJson']),
        ];
    }

    /**
     * @brief Resolve meta description for the active locale.
     *
     * @param string|null $locale Viewer locale code.
     * @param string $fallbackTranslationKey Messages key used when admin value is empty.
     * @return string Plain-text meta description.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function metaDescription(?string $locale, string $fallbackTranslationKey): string
    {
        return $this->siteSeoResolverService->resolveMetaDescription(
            $this->normalizeLocale($locale),
            $fallbackTranslationKey,
        );
    }

    /**
     * @brief Resolve CV meta description for the active locale.
     *
     * @param string|null $locale Viewer locale code.
     * @param string $fallbackTranslationKey Messages key used when admin or dynamic value is empty.
     * @param string|null $sectionTranslationKey Optional section label messages key.
     * @return string Plain-text meta description.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function cvMetaDescription(
        ?string $locale,
        string $fallbackTranslationKey,
        ?string $sectionTranslationKey = null,
    ): string {
        return $this->siteSeoResolverService->resolveCvMetaDescription(
            $this->normalizeLocale($locale),
            $fallbackTranslationKey,
            $sectionTranslationKey,
        );
    }

    /**
     * @brief Resolve home page title segment for the active locale.
     *
     * @param string|null $locale Viewer locale code.
     * @return string Plain-text title segment.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function homePageTitle(?string $locale): string
    {
        return $this->siteSeoResolverService->resolveHomePageTitle($this->normalizeLocale($locale));
    }

    /**
     * @brief Resolve CV page title segment for the active locale.
     *
     * @param string|null $locale Viewer locale code.
     * @param string|null $sectionTranslationKey Optional section label messages key.
     * @return string Plain-text title segment.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function cvPageTitle(?string $locale, ?string $sectionTranslationKey = null): string
    {
        return $this->siteSeoResolverService->resolveCvPageTitle(
            $this->normalizeLocale($locale),
            $sectionTranslationKey,
        );
    }

    /**
     * @brief Resolve hreflang alternate URLs for the current public page.
     *
     * @return list<array{locale: string, url: string}>
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function hreflangAlternates(): array
    {
        return $this->siteSeoResolverService->resolveHreflangAlternates();
    }

    /**
     * @brief Whether Open Graph tags should be rendered.
     *
     * @param string|null $locale Viewer locale code.
     * @return bool True when owner title and share image are configured.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function openGraphEnabled(?string $locale): bool
    {
        return $this->siteSeoResolverService->isOpenGraphEnabled($this->normalizeLocale($locale));
    }

    /**
     * @brief Resolve relative share image path for Open Graph when available.
     *
     * @return string|null Relative public asset path.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function shareImagePath(): ?string
    {
        return $this->siteSeoResolverService->resolveShareImageRelativePath();
    }

    /**
     * @brief Resolve canonical URL for the active locale and current public path.
     *
     * @param string|null $locale Viewer locale code.
     * @return string Absolute canonical URL.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function canonicalUrl(?string $locale): string
    {
        return $this->siteSeoResolverService->resolveCanonicalUrl($this->normalizeLocale($locale));
    }

    /**
     * @brief Resolve Twitter card type for the current share image.
     *
     * @return string Twitter card value.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function twitterCardType(): string
    {
        return $this->siteSeoResolverService->resolveTwitterCardType();
    }

    /**
     * @brief Resolve Open Graph locale code for the active viewer locale.
     *
     * @param string|null $locale Viewer locale code.
     * @return string Open Graph locale code.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function openGraphLocale(?string $locale): string
    {
        return $this->siteSeoResolverService->resolveOpenGraphLocale($this->normalizeLocale($locale));
    }

    /**
     * @brief Resolve alternate Open Graph locale codes.
     *
     * @param string|null $locale Active viewer locale code.
     * @return list<string>
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function openGraphLocaleAlternates(?string $locale): array
    {
        return $this->siteSeoResolverService->resolveOpenGraphLocaleAlternates($this->normalizeLocale($locale));
    }

    /**
     * @brief Resolve JSON-LD document for the current public page.
     *
     * @param string|null $locale Viewer locale code.
     * @param string|null $canonicalUrl Absolute canonical page URL.
     * @return string|null JSON string or null when structured data is unavailable.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function structuredDataJson(?string $locale, ?string $canonicalUrl): ?string
    {
        $normalizedCanonicalUrl = trim((string) $canonicalUrl);
        if ($normalizedCanonicalUrl === '') {
            return null;
        }

        return $this->siteStructuredDataService->resolveJsonLdDocument(
            $this->normalizeLocale($locale),
            $normalizedCanonicalUrl,
        );
    }

    /**
     * @brief Normalize locale code with French fallback.
     *
     * @param string|null $locale Raw locale value.
     * @return string Normalized locale code.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function normalizeLocale(?string $locale): string
    {
        $normalizedLocale = trim((string) ($locale ?? 'fr'));

        return $normalizedLocale !== '' ? $normalizedLocale : 'fr';
    }
}
