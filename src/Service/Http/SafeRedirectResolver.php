<?php

declare(strict_types=1);

namespace App\Service\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @brief Resolve safe same-origin redirect targets from HTTP Referer headers.
 */
final class SafeRedirectResolver
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @brief Resolve an internal redirect URL from Referer or fallback route.
     *
     * @param Request $request Current HTTP request.
     * @param string $fallbackRoute Symfony route used when Referer is missing or external.
     * @return string Internal path (and optional query) safe for RedirectResponse.
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function resolveInternalRedirect(Request $request, string $fallbackRoute = 'app_home'): string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return $this->urlGenerator->generate($fallbackRoute);
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        if (!is_string($refererHost) || $refererHost !== $request->getHost()) {
            return $this->urlGenerator->generate($fallbackRoute);
        }

        $path = parse_url($referer, PHP_URL_PATH) ?? '/';
        $query = parse_url($referer, PHP_URL_QUERY);

        return $path.($query !== null && $query !== '' ? '?'.$query : '');
    }
}
