<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @brief Build the public XML sitemap for indexable landing and CV routes.
 */
final class SiteSitemapService
{
    /**
     * @var list<array{route: string, priority: string, changefreq: string}>
     */
    private const PUBLIC_ENTRIES = [
        ['route' => 'app_home', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['route' => 'cv_show', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['route' => 'cv_situation', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['route' => 'cv_experience_full', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['route' => 'cv_education_full', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['route' => 'cv_certifications_full', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['route' => 'cv_skills_full', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['route' => 'cv_projects_full', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LocaleConfigurationService $localeConfigurationService,
    ) {
    }

    /**
     * @brief Render sitemap XML for all public indexable routes.
     *
     * @return string XML document body.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function buildXmlDocument(): string
    {
        $lastModified = (new \DateTimeImmutable())->format('Y-m-d');
        $entries = '';
        $origin = $this->resolveOrigin();
        $activeLocales = $this->resolveActiveLocales();

        foreach (self::PUBLIC_ENTRIES as $entry) {
            $relativePath = $this->urlGenerator->generate(
                $entry['route'],
                [],
                UrlGeneratorInterface::RELATIVE_PATH,
            );

            foreach ($activeLocales as $localeCode) {
                $location = $this->buildLocalizedPublicUrl($origin, $relativePath, $localeCode);
                $entries .= sprintf(
                    "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
                    htmlspecialchars($location, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($lastModified, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($entry['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($entry['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                );
            }
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$entries}</urlset>

XML;
    }

    /**
     * @brief Resolve absolute origin from router-generated home URL.
     *
     * @return string Origin without trailing slash.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveOrigin(): string
    {
        $homeUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $parts = parse_url($homeUrl);
        if (!is_array($parts)) {
            return 'http://localhost';
        }

        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'http';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : 'localhost';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * @brief Resolve active locale codes from site configuration.
     *
     * @return list<string>
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveActiveLocales(): array
    {
        $configuration = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($configuration['activeLocales'] ?? null) ? $configuration['activeLocales'] : [];
        if ($activeLocales === []) {
            $activeLocales = ['fr'];
        }

        return array_values(array_filter(
            $activeLocales,
            static fn (mixed $locale): bool => is_string($locale) && trim($locale) !== '',
        ));
    }

    /**
     * @brief Build a locale-prefixed public URL for sitemap entries.
     *
     * @param string $origin Absolute origin without trailing slash.
     * @param string $relativePath Route-relative path such as /cv/.
     * @param string $localeCode Target locale code.
     * @return string Absolute localized URL.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function buildLocalizedPublicUrl(string $origin, string $relativePath, string $localeCode): string
    {
        $normalizedPath = $relativePath === '/' ? '' : $relativePath;

        return rtrim($origin, '/').'/'.$localeCode.$normalizedPath;
    }
}
