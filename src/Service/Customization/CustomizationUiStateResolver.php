<?php

declare(strict_types=1);

namespace App\Service\Customization;

use App\Service\Cv\ExperienceContract;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Whitelist and resolve customization admin UI state for PRG redirects and Twig rendering.
 */
final class CustomizationUiStateResolver
{
    public const HOME_PANEL_DEFAULT = 'background';

    public const CV_TAB_DEFAULT = 'cv_data';

    /** @var list<string> */
    public const HOME_PANEL_KEYS = [
        'background',
        'signature',
        'texts',
        'webcv',
        'tiles',
        'custom_quick_tiles',
    ];

    public const CV_ABOUT_PANEL_DEFAULT = 'section';

    /** @var list<string> */
    public const CV_ABOUT_PANEL_KEYS = [
        'section',
        'photo',
        'presentation',
        'situation_content',
    ];

    /** @var list<string> */
    public const CV_SECTION_PANEL_KEYS = [
        'section',
    ];

    /** @brief Legacy alias kept for redirects from former Situation main tab. */
    public const CV_SITUATION_PANEL_DEFAULT = 'situation_content';

    public const CV_EXPERIENCE_PANEL_DEFAULT = 'professional_entries';

    /** @var list<string> */
    public const CV_EXPERIENCE_PANEL_KEYS = [
        'section',
        'professional_entries',
    ];

    public const CV_SKILLS_PANEL_DEFAULT = 'skills_catalog';

    /** @var list<string> */
    public const CV_SKILLS_PANEL_KEYS = [
        'skills_catalog',
    ];

    public const CV_EDUCATION_PANEL_DEFAULT = 'education_entries';

    /** @var list<string> */
    public const CV_EDUCATION_PANEL_KEYS = [
        'section',
        'education_entries',
    ];

    public const CV_CERTIFICATION_PANEL_DEFAULT = 'certification_entries';

    /** @var list<string> */
    public const CV_CERTIFICATION_PANEL_KEYS = [
        'section',
        'certification_entries',
    ];

    public const CV_LANGUAGES_PANEL_DEFAULT = 'languages_entries';

    /** @var list<string> */
    public const CV_LANGUAGES_PANEL_KEYS = [
        'section',
        'languages_entries',
    ];

    public const CV_INTERESTS_PANEL_DEFAULT = 'interests_entries';

    /** @var list<string> */
    public const CV_INTERESTS_PANEL_KEYS = [
        'section',
        'interests_entries',
    ];

    public const CV_WEB_PROFILES_PANEL_DEFAULT = 'web_profiles_entries';

    /** @var list<string> */
    public const CV_WEB_PROFILES_PANEL_KEYS = [
        'section',
        'web_profiles_entries',
    ];

    public const CV_REFERENCES_PANEL_DEFAULT = 'references_entries';

    /** @var list<string> */
    public const CV_REFERENCES_PANEL_KEYS = [
        'section',
        'references_entries',
    ];

    /** @var list<string> */
    public const CV_TAB_KEYS = [
        'cv_data',
        'about',
        'skills',
        'flagship_projects',
        'experience',
        'education',
        'certification',
        'languages',
        'interests',
        'web_profiles',
        'references',
    ];

    /**
     * @brief Resolve home customization panel and locale from raw inputs.
     *
     * @param string|null $panel Raw panel slug.
     * @param string|null $locale Raw locale code.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param string $defaultLocale Fallback locale when missing or invalid.
     * @return HomeUiState Validated home UI state.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function resolveHomeState(
        ?string $panel,
        ?string $locale,
        array $activeLocales,
        string $defaultLocale,
    ): HomeUiState {
        $resolvedPanel = $this->resolvePanel($panel, self::HOME_PANEL_KEYS, self::HOME_PANEL_DEFAULT);
        $resolvedLocale = $this->resolveLocale($locale, $activeLocales, $defaultLocale);

        return new HomeUiState($resolvedPanel, $resolvedLocale);
    }

    /**
     * @brief Resolve CV customization tab, optional accordion panel, and optional locale tab.
     *
     * @param string|null $tab Raw main tab slug.
     * @param string|null $panel Raw About or Situation accordion panel slug.
     * @param string|null $locale Raw locale tab code.
     * @param string|null $entry Raw experience entry UUID for entry accordion deep link.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param string $defaultLocale Fallback locale when missing or invalid.
     * @return CvUiState Validated CV UI state.
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function resolveCvState(
        ?string $tab,
        ?string $panel,
        ?string $locale,
        array $activeLocales,
        string $defaultLocale,
        ?string $entry = null,
    ): CvUiState {
        if (is_string($tab) && trim($tab) === 'page_title') {
            $tab = 'cv_data';
        }

        if (is_string($tab) && trim($tab) === 'situation') {
            $tab = 'about';
            $panelValue = is_string($panel) ? trim($panel) : '';
            if ($panelValue === '' || $panelValue === 'section_background') {
                $panel = self::CV_SITUATION_PANEL_DEFAULT;
            }
        }

        $resolvedTab = $this->resolvePanel($tab, self::CV_TAB_KEYS, self::CV_TAB_DEFAULT);
        $resolvedLocale = $this->resolveLocale($locale, $activeLocales, $defaultLocale);

        $resolvedPanel = null;
        if ($resolvedTab === 'about') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_ABOUT_PANEL_KEYS, self::CV_ABOUT_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'experience') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_EXPERIENCE_PANEL_KEYS, self::CV_EXPERIENCE_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'education') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_EDUCATION_PANEL_KEYS, self::CV_EDUCATION_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'certification') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_CERTIFICATION_PANEL_KEYS, self::CV_CERTIFICATION_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'languages') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_LANGUAGES_PANEL_KEYS, self::CV_LANGUAGES_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'interests') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_INTERESTS_PANEL_KEYS, self::CV_INTERESTS_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'web_profiles') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_WEB_PROFILES_PANEL_KEYS, self::CV_WEB_PROFILES_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'references') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_REFERENCES_PANEL_KEYS, self::CV_REFERENCES_PANEL_DEFAULT);
        } elseif ($resolvedTab === 'skills') {
            $resolvedPanel = $this->resolvePanel($panel, self::CV_SKILLS_PANEL_KEYS, self::CV_SKILLS_PANEL_DEFAULT);
        } elseif (in_array($resolvedTab, ['flagship_projects'], true)) {
            $resolvedPanel = null;
        }

        if (!in_array($resolvedTab, ['about', 'experience', 'education', 'certification', 'interests', 'references', 'cv_data', 'skills'], true)) {
            $resolvedLocale = $this->resolveLocale(null, $activeLocales, $defaultLocale);
        }

        if ($resolvedTab === 'cv_data') {
            $resolvedPanel = null;
        }

        $resolvedEntry = $this->resolveEntryDeepLinkId($entry, $resolvedTab);

        if ($resolvedTab === 'experience' && $resolvedEntry !== null) {
            $resolvedPanel = self::CV_EXPERIENCE_PANEL_DEFAULT;
        }

        return new CvUiState($resolvedTab, $resolvedPanel, $resolvedLocale, $resolvedEntry);
    }

    /**
     * @brief Resolve home UI state from GET query or POST hidden fields.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param string $defaultLocale Site default locale.
     * @return HomeUiState Validated home UI state.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function resolveHomeFromRequest(Request $request, array $activeLocales, string $defaultLocale): HomeUiState
    {
        return $this->resolveHomeState(
            $this->extractParam($request, 'panel', 'customization_panel'),
            $this->extractParam($request, 'locale', 'customization_locale'),
            $activeLocales,
            $defaultLocale,
        );
    }

    /**
     * @brief Resolve CV UI state from GET query or POST hidden fields.
     *
     * @param Request $request HTTP request.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param string $defaultLocale Site default locale.
     * @return CvUiState Validated CV UI state.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function resolveCvFromRequest(Request $request, array $activeLocales, string $defaultLocale): CvUiState
    {
        return $this->resolveCvState(
            $this->extractParam($request, 'tab', 'customization_tab'),
            $this->extractParam($request, 'panel', 'customization_panel'),
            $this->extractParam($request, 'locale', 'customization_locale'),
            $activeLocales,
            $defaultLocale,
            $this->extractParam($request, 'entry', 'customization_entry'),
        );
    }

    /**
     * @brief Build redirect query parameters for home customization route.
     *
     * @param HomeUiState $state Resolved home UI state.
     * @return array<string, string> Route parameters for redirectToRoute.
     * @date 2026-05-17
     * @author Stephane H.
     */
    public function buildHomeRedirectParams(HomeUiState $state): array
    {
        $params = ['panel' => $state->panel];
        if ($state->panel === 'texts') {
            $params['locale'] = $state->locale;
        }

        return $params;
    }

    /**
     * @brief Build redirect query parameters for CV customization index route.
     *
     * @param CvUiState $state Resolved CV UI state.
     * @return array<string, string> Route parameters for redirectToRoute.
     * @date 2026-05-29
     * @author Stephane H.
     */
    public function buildCvRedirectParams(CvUiState $state): array
    {
        $params = ['tab' => $state->tab];

        if ($state->tab === 'about' && $state->panel !== null) {
            $params['panel'] = $state->panel;
            if (
                in_array($state->panel, ['presentation', 'situation_content'], true)
                && $state->locale !== null
            ) {
                $params['locale'] = $state->locale;
            }
        }

        if ($state->tab === 'experience') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
            if ($state->locale !== null) {
                $params['locale'] = $state->locale;
            }
            if ($state->entry !== null) {
                $params['entry'] = $state->entry;
            }
        }

        if ($state->tab === 'education') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
            if ($state->locale !== null) {
                $params['locale'] = $state->locale;
            }
            if ($state->entry !== null) {
                $params['entry'] = $state->entry;
            }
        }

        if ($state->tab === 'certification') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
            if ($state->locale !== null) {
                $params['locale'] = $state->locale;
            }
            if ($state->entry !== null) {
                $params['entry'] = $state->entry;
            }
        }

        if ($state->tab === 'languages') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
        }

        if ($state->tab === 'interests') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
            if ($state->locale !== null) {
                $params['locale'] = $state->locale;
            }
        }

        if ($state->tab === 'web_profiles') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
        }

        if ($state->tab === 'references') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
            if ($state->locale !== null) {
                $params['locale'] = $state->locale;
            }
        }

        if ($state->tab === 'skills') {
            if ($state->panel !== null) {
                $params['panel'] = $state->panel;
            }
            if ($state->panel === 'skills_catalog' && $state->locale !== null) {
                $params['locale'] = $state->locale;
            }
        }

        if ($state->tab === 'flagship_projects' && $state->entry !== null) {
            $params['entry'] = $state->entry;
        }

        if ($state->tab === 'cv_data' && $state->locale !== null) {
            $params['locale'] = $state->locale;
        }

        return $params;
    }

    /**
     * @brief Normalize a raw panel or tab slug against a whitelist.
     *
     * @param string|null $raw Raw slug.
     * @param list<string> $allowed Allowed slugs.
     * @param string $default Default when missing or invalid.
     * @return string Valid slug.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function resolvePanel(?string $raw, array $allowed, string $default): string
    {
        $normalized = is_string($raw) ? trim($raw) : '';
        if ($normalized !== '' && in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        return $default;
    }

    /**
     * @brief Normalize locale code against active site locales.
     *
     * @param string|null $raw Raw locale code.
     * @param list<string> $activeLocales Allowed locale codes.
     * @param string $defaultLocale Fallback locale.
     * @return string Valid locale code.
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function resolveLocale(?string $raw, array $activeLocales, string $defaultLocale): string
    {
        $normalized = is_string($raw) ? strtolower(trim($raw)) : '';
        foreach ($activeLocales as $activeLocale) {
            if (is_string($activeLocale) && strtolower($activeLocale) === $normalized) {
                return strtolower($activeLocale);
            }
        }

        $defaultNormalized = strtolower(trim($defaultLocale));
        foreach ($activeLocales as $activeLocale) {
            if (is_string($activeLocale) && strtolower($activeLocale) === $defaultNormalized) {
                return strtolower($activeLocale);
            }
        }

        $first = $activeLocales[0] ?? 'fr';

        return is_string($first) ? strtolower($first) : 'fr';
    }

    /**
     * @brief Read a query or request parameter by public and hidden field names.
     *
     * On POST, the request body (hidden fields) takes precedence over the query string so PRG
     * redirects preserve the accordion panel submitted with the form.
     *
     * @param Request $request HTTP request.
     * @param string $queryKey Query string key.
     * @param string $requestKey POST body key for hidden fields.
     * @return string|null Trimmed value or null when empty.
     * @date 2026-05-20
     * @author Stephane H.
     */
    private function extractParam(Request $request, string $queryKey, string $requestKey): ?string
    {
        if ($request->isMethod('POST')) {
            $bodyValue = $request->request->get($requestKey);
            if (is_string($bodyValue) && trim($bodyValue) !== '') {
                return trim($bodyValue);
            }
        }

        $queryValue = $request->query->get($queryKey);
        if (is_string($queryValue) && trim($queryValue) !== '') {
            return trim($queryValue);
        }

        if (!$request->isMethod('POST')) {
            $bodyValue = $request->request->get($requestKey);
            if (is_string($bodyValue) && trim($bodyValue) !== '') {
                return trim($bodyValue);
            }
        }

        return null;
    }

    /**
     * @brief Normalize optional entry UUID for accordion deep links on supported CV tabs.
     *
     * @param string|null $raw Raw entry id from query or hidden field.
     * @param string $tab Resolved CV main tab slug.
     * @return string|null Valid UUID or null when absent or invalid.
     * @date 2026-06-08
     * @author Stephane H.
     */
    private function resolveEntryDeepLinkId(?string $raw, string $tab): ?string
    {
        if (!in_array($tab, ['experience', 'flagship_projects', 'education', 'certification', 'references'], true)) {
            return null;
        }

        $normalized = is_string($raw) ? trim($raw) : '';
        if ($normalized === '') {
            return null;
        }

        return ExperienceContract::isValidUuid($normalized) ? $normalized : null;
    }
}
