<?php

namespace App\Controller;

use App\EventSubscriber\EmploymentCompanyLocaleSubscriber;
use App\Service\Http\SafeRedirectResolver;
use App\Service\Locale\LocaleCodeNormalizer;
use App\Service\Locale\LocaleConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller LocaleController.
 */
class LocaleController extends AbstractController
{
    /**
     * @brief Build locale controller.
     * @param LocaleConfigurationService $localeConfigurationService Locale configuration service.
     * @param array<int, string> $supportedLocales Supported locales.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function __construct(
        private readonly LocaleConfigurationService $localeConfigurationService,
        private readonly SafeRedirectResolver $safeRedirectResolver,
        private readonly LocaleCodeNormalizer $localeCodeNormalizer,
        private readonly array $supportedLocales = ['fr', 'en', 'de', 'lt', 'nb']
    ) {
    }

    /**
     * @brief Persist selected locale and redirect back.
     * @param string $locale Locale candidate.
     * @param Request $request Current request.
     * @return Response
     * @date 2026-05-08
     * @author Stephane H.
     */
    #[Route('/locale/{locale}', name: 'locale_switch', methods: ['GET'])]
    public function switch(string $locale, Request $request): Response
    {
        $configuration = $this->localeConfigurationService->getConfiguration();
        $activeLocales = is_array($configuration['activeLocales'] ?? null) ? $configuration['activeLocales'] : $this->supportedLocales;
        $fallbackLocale = is_string($configuration['defaultLocale'] ?? null) ? $configuration['defaultLocale'] : 'en';
        $normalizedLocale = $this->normalizeLocale($locale, $activeLocales);
        if ($normalizedLocale === null) {
            $this->addFlash('warning', 'locale.invalid');
            $normalizedLocale = $this->normalizeLocale($fallbackLocale, $activeLocales) ?? ($activeLocales[0] ?? 'en');
        }

        $targetUrl = $this->safeRedirectResolver->resolveInternalRedirect($request, 'app_home');
        $targetUrl = $this->withLangQueryOnCvPath($targetUrl, $normalizedLocale);

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $normalizedLocale);
            if ($this->isCvPath($targetUrl)) {
                $request->getSession()->set(
                    EmploymentCompanyLocaleSubscriber::SESSION_LOCALE_OVERRIDE,
                    $normalizedLocale,
                );
            }
        }

        $response = new RedirectResponse($targetUrl);
        $response->headers->setCookie(
            Cookie::create('site_locale')
                ->withValue($normalizedLocale)
                ->withExpires(strtotime('+1 year'))
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                ->withSameSite('lax')
        );

        return $response;
    }

    /**
     * @brief Add or replace lang= on CV redirect targets so company locale does not wipe the choice.
     *
     * @param string $url Internal redirect path (optional query).
     * @param string $locale Selected locale code.
     * @return string Redirect URL, with lang on /cv paths.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function withLangQueryOnCvPath(string $url, string $locale): string
    {
        if (!$this->isCvPath($url)) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $params = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
        }
        $params['lang'] = $locale;

        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        $result = $path.'?'.http_build_query($params);
        if (is_string($fragment) && $fragment !== '') {
            $result .= '#'.$fragment;
        }

        return $result;
    }

    /**
     * @brief Detect whether a redirect URL targets the public CV area.
     *
     * @param string $url Internal redirect path (optional query).
     * @return bool True when the path is under /cv.
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function isCvPath(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/cv');
    }

    /**
     * @brief Normalize locale to 2-char supported format.
     * @param string $locale Raw locale value.
     * @param array<int, string> $allowedLocales Allowed locales.
     * @return string|null
     * @date 2026-05-08
     * @author Stephane H.
     */
    private function normalizeLocale(string $locale, array $allowedLocales): ?string
    {
        return $this->localeCodeNormalizer->normalizeToSupported($locale, $allowedLocales);
    }
}
