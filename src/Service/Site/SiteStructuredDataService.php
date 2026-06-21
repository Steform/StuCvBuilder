<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Repository\CvProfileRepository;
use App\Service\Cv\CvPublicIdentityPlaceholderService;
use App\Service\Cv\WebProfilesContract;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @brief Build Schema.org JSON-LD graphs for public home and CV pages.
 */
final class SiteStructuredDataService
{
    /**
     * @var array<string, string>
     */
    private const ROUTE_PAGE_TYPES = [
        'app_home' => 'WebPage',
        'cv_show' => 'ProfilePage',
        'cv_situation' => 'ProfilePage',
        'cv_experience_full' => 'ProfilePage',
        'cv_education_full' => 'ProfilePage',
        'cv_certifications_full' => 'ProfilePage',
        'cv_skills_full' => 'ProfilePage',
        'cv_projects_full' => 'ProfilePage',
    ];

    public function __construct(
        private readonly SiteSeoResolverService $siteSeoResolverService,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly CvPublicIdentityPlaceholderService $cvPublicIdentityPlaceholderService,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @brief Resolve a JSON-LD document for the current public page when identity is configured.
     *
     * @param string $locale Active viewer locale.
     * @param string $canonicalUrl Absolute canonical page URL.
     * @return string|null JSON string suitable for a script[type=application/ld+json] tag.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveJsonLdDocument(string $locale, string $canonicalUrl): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $routeName = $request->attributes->get('_route');
        if (!is_string($routeName) || !isset(self::ROUTE_PAGE_TYPES[$routeName])) {
            return null;
        }

        if (!$this->siteSeoResolverService->hasConfiguredDisplayName()) {
            return null;
        }

        $canonicalUrl = trim($canonicalUrl);
        if ($canonicalUrl === '') {
            return null;
        }

        $localeContext = $this->resolveLocaleContext();
        $payload = $this->resolveLatestPayload();
        $personId = rtrim($canonicalUrl, '/').'#person';
        $person = $this->buildPersonNode(
            $payload,
            $locale,
            $localeContext['activeLocales'],
            $localeContext['defaultLocale'],
            $personId,
            $request->getSchemeAndHttpHost(),
        );
        if ($person === null) {
            return null;
        }

        $graph = [
            $person,
            [
                '@type' => self::ROUTE_PAGE_TYPES[$routeName],
                '@id' => rtrim($canonicalUrl, '/').'#webpage',
                'url' => $canonicalUrl,
                'inLanguage' => $locale,
                'mainEntity' => ['@id' => $personId],
            ],
        ];

        $encoded = json_encode(
            [
                '@context' => 'https://schema.org',
                '@graph' => $graph,
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @brief Build the Person node for structured data output.
     *
     * @param array<string, mixed> $payload Latest CV profile payload.
     * @param string $locale Active viewer locale.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param string $personId Stable @id for the Person entity.
     * @param string $schemeAndHost Request scheme and host for absolute image URLs.
     * @return array<string, mixed>|null Person node or null when display name is missing.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function buildPersonNode(
        array $payload,
        string $locale,
        array $activeLocales,
        string $defaultLocale,
        string $personId,
        string $schemeAndHost,
    ): ?array {
        $displayName = trim($this->cvPublicIdentityPlaceholderService->resolveDisplayNamePlain($payload, $locale));
        if ($displayName === '') {
            return null;
        }

        $person = [
            '@type' => 'Person',
            '@id' => $personId,
            'name' => $displayName,
            'url' => rtrim($schemeAndHost, '/').'/'.$locale.'/cv/',
        ];

        $jobTitle = $this->cvPublicIdentityPlaceholderService->resolveSoughtPositionPlain(
            $payload,
            $locale,
            $activeLocales,
            $defaultLocale,
        );
        if ($jobTitle !== '') {
            $person['jobTitle'] = $jobTitle;
        }

        $location = $this->cvPublicIdentityPlaceholderService->resolveAboutHeaderLocationLine(
            $payload,
            $locale,
            $activeLocales,
            $defaultLocale,
        );
        if ($location !== '') {
            $person['homeLocation'] = [
                '@type' => 'Place',
                'name' => $location,
            ];
        }

        $sameAs = $this->resolveSameAsUrls($payload);
        if ($sameAs !== []) {
            $person['sameAs'] = $sameAs;
        }

        $shareImagePath = $this->siteSeoResolverService->resolveShareImageRelativePath();
        if (is_string($shareImagePath) && $shareImagePath !== '') {
            $person['image'] = rtrim($schemeAndHost, '/').'/'.ltrim($shareImagePath, '/');
        }

        return $person;
    }

    /**
     * @brief Collect visible web profile URLs for schema.org sameAs.
     *
     * @param array<string, mixed> $payload Latest CV profile payload.
     * @return list<string>
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveSameAsUrls(array $payload): array
    {
        $entries = WebProfilesContract::filterVisible(
            WebProfilesContract::entriesFromStoredPayload($payload),
        );

        $urls = [];
        foreach ($entries as $entry) {
            $url = is_string($entry['url'] ?? null) ? trim($entry['url']) : '';
            if ($url === '' || in_array($url, $urls, true)) {
                continue;
            }

            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * @brief Decode the latest persisted CV profile payload.
     *
     * @param void No input parameter.
     * @return array<string, mixed>
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveLatestPayload(): array
    {
        $profile = $this->cvProfileRepository->findOneBy([], ['id' => 'DESC']);
        if ($profile === null) {
            return [];
        }

        $decoded = json_decode($profile->getContentJson(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @brief Resolve active locales and default locale from site configuration.
     *
     * @param void No input parameter.
     * @return array{activeLocales: list<string>, defaultLocale: string}
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveLocaleContext(): array
    {
        $configuration = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($configuration['activeLocales'] ?? null) ? $configuration['activeLocales'] : [];
        $defaultLocale = is_string($configuration['defaultLocale'] ?? null) ? $configuration['defaultLocale'] : 'fr';
        if ($activeLocales === []) {
            $activeLocales = ['fr'];
        }

        return [
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
        ];
    }
}
