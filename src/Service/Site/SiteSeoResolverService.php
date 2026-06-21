<?php

declare(strict_types=1);

namespace App\Service\Site;

use App\Cv\SkillsTreeContract;
use App\Repository\CvProfileRepository;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Cv\CvPublicIdentityPlaceholderService;
use App\Service\Home\HomeCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use App\Site\SiteSeoContract;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Resolve configurable SEO meta descriptions and conditional Open Graph context.
 */
final class SiteSeoResolverService
{
    /**
     * @var list<string>
     */
    private const HREFLANG_ROUTE_NAMES = [
        'app_home',
        'cv_show',
        'cv_situation',
        'cv_experience_full',
        'cv_education_full',
        'cv_certifications_full',
        'cv_skills_full',
        'cv_projects_full',
    ];

    public function __construct(
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly CvProfileRepository $cvProfileRepository,
        private readonly TranslatorInterface $translator,
        private readonly CvPublicIdentityPlaceholderService $cvPublicIdentityPlaceholderService,
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @brief Resolve meta description for a public page using admin override then translation fallback.
     *
     * @param string $locale Active viewer locale.
     * @param string $fallbackTranslationKey Symfony messages key used when admin value is empty.
     * @return string Plain-text meta description.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveMetaDescription(string $locale, string $fallbackTranslationKey): string
    {
        $configured = $this->homeCustomizationService->resolveMetaDescriptionForLocale($locale);
        if ($configured !== '') {
            return $configured;
        }

        return $this->translator->trans($fallbackTranslationKey, [], 'messages', $locale);
    }

    /**
     * @brief Resolve CV-facing meta description with admin override, dynamic identity, then fallback key.
     *
     * @param string $locale Active viewer locale.
     * @param string $fallbackTranslationKey Messages key when no admin or dynamic value exists.
     * @param string|null $sectionTranslationKey Optional section label key prefixed to the description.
     * @return string Plain-text meta description.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveCvMetaDescription(
        string $locale,
        string $fallbackTranslationKey,
        ?string $sectionTranslationKey = null,
    ): string {
        $configured = $this->homeCustomizationService->resolveMetaDescriptionForLocale($locale);
        $description = $configured !== ''
            ? $configured
            : $this->buildDynamicCvMetaDescription($locale);

        if ($description === '') {
            $description = $this->translator->trans($fallbackTranslationKey, [], 'messages', $locale);
        }

        if ($sectionTranslationKey !== null && trim($sectionTranslationKey) !== '') {
            $sectionLabel = $this->translator->trans($sectionTranslationKey, [], 'messages', $locale);
            $description = $sectionLabel.' — '.$description;
        }

        return self::truncateMetaDescription($description, SiteSeoContract::SERP_META_DESCRIPTION_TARGET);
    }

    /**
     * @brief Resolve the home `<title>` block segment (owner prefix is added by the layout).
     *
     * @param string $locale Active viewer locale.
     * @return string Plain-text title segment.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveHomePageTitle(string $locale): string
    {
        $localeContext = $this->resolveLocaleContext();
        $payload = $this->resolveLatestPayload();
        $position = $this->cvPublicIdentityPlaceholderService->resolveSoughtPositionPlain(
            $payload,
            $locale,
            $localeContext['activeLocales'],
            $localeContext['defaultLocale'],
        );

        if ($position !== '') {
            return self::truncateTitleSegment($position);
        }

        return self::truncateTitleSegment(
            $this->translator->trans('home.meta.title', [], 'messages', $locale)
        );
    }

    /**
     * @brief Resolve the CV `<title>` block segment (owner prefix is added by the layout).
     *
     * @param string $locale Active viewer locale.
     * @param string|null $sectionTranslationKey Optional section label key rendered before the page title.
     * @return string Plain-text title segment.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveCvPageTitle(string $locale, ?string $sectionTranslationKey = null): string
    {
        $localeContext = $this->resolveLocaleContext();
        $payload = $this->resolveLatestPayload();
        $pageTitle = $this->resolveLocalizedPageTitle(
            $payload,
            $locale,
            $localeContext['defaultLocale'],
        );

        if ($pageTitle === '') {
            $pageTitle = $this->cvPublicIdentityPlaceholderService->resolveSoughtPositionPlain(
                $payload,
                $locale,
                $localeContext['activeLocales'],
                $localeContext['defaultLocale'],
            );
        }

        if ($pageTitle === '') {
            $pageTitle = $this->translator->trans('cv.meta.title', [], 'messages', $locale);
        }

        $pageTitle = self::truncateTitleSegment($pageTitle);

        if ($sectionTranslationKey !== null && trim($sectionTranslationKey) !== '') {
            $sectionLabel = $this->translator->trans($sectionTranslationKey, [], 'messages', $locale);

            return self::truncateTitleSegment($sectionLabel.' — '.$pageTitle);
        }

        return $pageTitle;
    }

    /**
     * @brief Build hreflang alternate URLs for the current public request path.
     *
     * @param void No input parameter.
     * @return list<array{locale: string, url: string}>
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveHreflangAlternates(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return [];
        }

        $routeName = $request->attributes->get('_route');
        if (!is_string($routeName) || !in_array($routeName, self::HREFLANG_ROUTE_NAMES, true)) {
            return [];
        }

        $localeContext = $this->resolveLocaleContext();
        $activeLocales = $localeContext['activeLocales'];
        $defaultLocale = $localeContext['defaultLocale'];
        if ($activeLocales === []) {
            return [];
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $pathInfo = $request->getPathInfo();
        $alternates = [];

        foreach ($activeLocales as $localeCode) {
            if (!is_string($localeCode) || trim($localeCode) === '') {
                continue;
            }

            $alternates[] = [
                'locale' => $localeCode,
                'url' => $this->buildLocalizedPublicUrl($baseUrl, $pathInfo, $localeCode),
            ];
        }

        $alternates[] = [
            'locale' => 'x-default',
            'url' => $this->buildLocalizedPublicUrl($baseUrl, $pathInfo, $defaultLocale),
        ];

        return $alternates;
    }

    /**
     * @brief Resolve canonical URL for the current public page using locale path prefixes.
     *
     * @param string $locale Active viewer locale.
     * @return string Absolute canonical URL.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveCanonicalUrl(string $locale): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        return $this->buildLocalizedPublicUrl(
            $request->getSchemeAndHttpHost(),
            $request->getPathInfo(),
            $locale,
        );
    }

    /**
     * @brief Resolve Open Graph locale code for the active viewer locale.
     *
     * @param string $locale Active viewer locale.
     * @return string Open Graph locale such as fr_FR.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveOpenGraphLocale(string $locale): string
    {
        return match ($locale) {
            'fr' => 'fr_FR',
            'en' => 'en_US',
            'de' => 'de_DE',
            'lt' => 'lt_LT',
            'no' => 'nb_NO',
            default => $locale.'_'.strtoupper($locale),
        };
    }

    /**
     * @brief Resolve alternate Open Graph locale codes excluding the active locale.
     *
     * @param string $locale Active viewer locale.
     * @return list<string>
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveOpenGraphLocaleAlternates(string $locale): array
    {
        $localeContext = $this->resolveLocaleContext();
        $alternates = [];

        foreach ($localeContext['activeLocales'] as $localeCode) {
            if (!is_string($localeCode) || $localeCode === '' || $localeCode === $locale) {
                continue;
            }

            $alternates[] = $this->resolveOpenGraphLocale($localeCode);
        }

        return $alternates;
    }

    /**
     * @brief Resolve Twitter card type based on share image dimensions.
     *
     * @param void No input parameter.
     * @return string Twitter card value (`summary` or `summary_large_image`).
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveTwitterCardType(): string
    {
        $shareImagePath = $this->resolveShareImageRelativePath();
        if ($shareImagePath === null) {
            return 'summary';
        }

        return $this->homeCustomizationService->isLargeFormatShareImage($shareImagePath)
            ? 'summary_large_image'
            : 'summary';
    }

    /**
     * @brief Whether Open Graph and Twitter cards should render for the current site state.
     *
     * @param string $locale Active viewer locale.
     * @return bool True when both a configured owner title and a share image exist.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function isOpenGraphEnabled(string $locale): bool
    {
        return $this->hasConfiguredDisplayName() && $this->resolveShareImageRelativePath() !== null;
    }

    /**
     * @brief Resolve relative public path for an Open Graph share image when available.
     *
     * @param void No input parameter.
     * @return string|null Relative path under public/ suitable for asset().
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveShareImageRelativePath(): ?string
    {
        $openGraphPath = $this->homeCustomizationService->resolveOpenGraphImageRelativePath();
        if (is_string($openGraphPath) && $openGraphPath !== '') {
            return $openGraphPath;
        }

        $customization = $this->homeCustomizationService->getOrCreateSingleton();

        $signaturePath = $this->homeCustomizationService->resolveSignatureImageRelativePath($customization, false);
        if (is_string($signaturePath) && $signaturePath !== '') {
            return $signaturePath;
        }

        $faviconPath = $this->homeCustomizationService->resolveSiteFaviconRelativePath();
        if ($faviconPath !== HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH) {
            return $faviconPath;
        }

        return null;
    }

    /**
     * @brief Whether CV public identity contains a persisted display name.
     *
     * @param void No input parameter.
     * @return bool True when displayName is configured in the latest CvProfile payload.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function hasConfiguredDisplayName(): bool
    {
        $payload = $this->resolveLatestPayload();
        $identity = $payload[CvPublicIdentityContract::KEY_ROOT] ?? null;
        if (!is_array($identity)) {
            return false;
        }

        $displayName = $identity[CvPublicIdentityContract::FIELD_DISPLAY_NAME] ?? null;

        return is_string($displayName) && trim($displayName) !== '';
    }

    /**
     * @brief Normalize admin-submitted meta description text.
     *
     * @param mixed $rawValue Raw request value.
     * @return string Sanitized meta description or empty string.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function normalizeMetaDescription(mixed $rawValue): string
    {
        if (!is_string($rawValue)) {
            return '';
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $rawValue) ?? '');
        if ($normalized === '') {
            return '';
        }

        return self::truncateMetaDescription($normalized, SiteSeoContract::MAX_META_DESCRIPTION_LENGTH);
    }

    /**
     * @brief Truncate a title segment for SERP display.
     *
     * @param string $value Raw title segment.
     * @return string Truncated plain-text title segment.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function truncateTitleSegment(string $value): string
    {
        return self::truncateMetaDescription($value, SiteSeoContract::MAX_TITLE_SEGMENT_LENGTH);
    }

    /**
     * @brief Truncate meta description text to a maximum length without breaking multibyte chars.
     *
     * @param string $value Raw description text.
     * @param int $maxLength Maximum allowed length.
     * @return string Truncated plain-text description.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public static function truncateMetaDescription(string $value, int $maxLength): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            $normalized = mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    /**
     * @brief Build a dynamic CV meta description from public identity and primary skills.
     *
     * @param string $locale Active viewer locale.
     * @return string Plain-text description or empty string when no data is available.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function buildDynamicCvMetaDescription(string $locale): string
    {
        $localeContext = $this->resolveLocaleContext();
        $payload = $this->resolveLatestPayload();
        $displayName = trim($this->cvPublicIdentityPlaceholderService->resolveDisplayNamePlain($payload, $locale));
        $fallbackName = $this->translator->trans(
            'cv.about.presentation_default.fallback_display_name',
            [],
            'messages',
            $locale,
        );
        if ($displayName === $fallbackName) {
            $displayName = '';
        }

        $position = $this->cvPublicIdentityPlaceholderService->resolveSoughtPositionPlain(
            $payload,
            $locale,
            $localeContext['activeLocales'],
            $localeContext['defaultLocale'],
        );
        $location = $this->cvPublicIdentityPlaceholderService->resolveAboutHeaderLocationLine(
            $payload,
            $locale,
            $localeContext['activeLocales'],
            $localeContext['defaultLocale'],
        );

        $segments = [];
        if ($displayName !== '' && $position !== '') {
            $segments[] = $displayName.' — '.$position;
        } elseif ($displayName !== '') {
            $segments[] = $displayName;
        } elseif ($position !== '') {
            $segments[] = $position;
        }

        if ($location !== '') {
            $segments[] = $location;
        }

        $skills = $this->extractPrimarySkillLabels(
            $payload,
            $locale,
            $localeContext['activeLocales'],
            $localeContext['defaultLocale'],
            2,
        );
        if ($skills !== []) {
            $segments[] = $this->translator->trans(
                'site.seo.skills_prefix',
                ['%skills%' => implode(', ', $skills)],
                'messages',
                $locale,
            );
        }

        if ($segments === []) {
            return '';
        }

        return implode('. ', $segments);
    }

    /**
     * @brief Resolve localized CV page title stored in profile payload.
     *
     * @param array<string, mixed> $payload Latest CV payload.
     * @param string $locale Active viewer locale.
     * @param string $defaultLocale Site default locale.
     * @return string Plain-text page title or empty string.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function resolveLocalizedPageTitle(array $payload, string $locale, string $defaultLocale): string
    {
        $pageTitles = $payload['pageTitleByLocale'] ?? null;
        if (!is_array($pageTitles)) {
            return '';
        }

        $candidate = $pageTitles[$locale] ?? $pageTitles[$defaultLocale] ?? null;

        return is_string($candidate) ? trim($candidate) : '';
    }

    /**
     * @brief Collect up to N primary skill labels from the resolved skills tree.
     *
     * @param array<string, mixed> $payload Latest CV payload.
     * @param string $locale Active viewer locale.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param int $limit Maximum number of labels to return.
     * @return list<string>
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function extractPrimarySkillLabels(
        array $payload,
        string $locale,
        array $activeLocales,
        string $defaultLocale,
        int $limit,
    ): array {
        $catalog = SkillsTreeContract::resolveCatalogFromPayload(
            $payload,
            $activeLocales,
            $defaultLocale,
            $this->translator,
        );
        $treePrimary = SkillsTreeContract::filterForPrimary($catalog, $locale, $defaultLocale);
        $labels = [];
        $this->collectPublicSkillLabelsFromCategories(
            is_array($treePrimary['categories'] ?? null) ? $treePrimary['categories'] : [],
            $labels,
            $limit,
        );

        return $labels;
    }

    /**
     * @brief Collect skill labels from public primary categories tree.
     *
     * @param list<array<string, mixed>> $categories Primary skills categories.
     * @param list<string> $labels Collected labels passed by reference.
     * @param int $limit Maximum number of labels to collect.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function collectPublicSkillLabelsFromCategories(array $categories, array &$labels, int $limit): void
    {
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $this->collectPublicSkillLabelsFromItems(
                is_array($category['items'] ?? null) ? $category['items'] : [],
                $labels,
                $limit,
            );

            foreach (is_array($category['subcategories'] ?? null) ? $category['subcategories'] : [] as $subcategory) {
                if (!is_array($subcategory)) {
                    continue;
                }

                $this->collectPublicSkillLabelsFromItems(
                    is_array($subcategory['items'] ?? null) ? $subcategory['items'] : [],
                    $labels,
                    $limit,
                );

                foreach (is_array($subcategory['groups'] ?? null) ? $subcategory['groups'] : [] as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    $this->collectPublicSkillLabelsFromItems(
                        is_array($group['items'] ?? null) ? $group['items'] : [],
                        $labels,
                        $limit,
                    );
                }
            }

            if (count($labels) >= $limit) {
                return;
            }
        }
    }

    /**
     * @brief Collect plain skill labels from one items list.
     *
     * @param list<array<string, mixed>> $items Skill item nodes.
     * @param list<string> $labels Collected labels passed by reference.
     * @param int $limit Maximum number of labels to collect.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function collectPublicSkillLabelsFromItems(array $items, array &$labels, int $limit): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '' || in_array($label, $labels, true)) {
                continue;
            }

            $labels[] = $label;
            if (count($labels) >= $limit) {
                return;
            }
        }
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

    /**
     * @brief Build a locale-prefixed public URL for canonical and hreflang output.
     *
     * @param string $schemeAndHost Absolute origin without trailing slash.
     * @param string $pathInfo Current request path without locale prefix.
     * @param string $localeCode Target locale code.
     * @return string Absolute localized URL.
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function buildLocalizedPublicUrl(string $schemeAndHost, string $pathInfo, string $localeCode): string
    {
        $normalizedPath = $pathInfo === '/' ? '' : $pathInfo;

        return rtrim($schemeAndHost, '/').'/'.$localeCode.$normalizedPath;
    }
}
