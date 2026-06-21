<?php

declare(strict_types=1);

namespace App\Service\Employment;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves visitor country from reverse-proxy headers.
 */
class VisitorCountryResolver
{
    /**
     * @brief Resolve ISO country code from request headers.
     *
     * @param Request $request HTTP request.
     * @return string|null Uppercase ISO code or null.
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolve(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Country-Code', 'X-App-Country'] as $header) {
            $value = strtoupper(trim((string) $request->headers->get($header, '')));
            if ($value !== '' && preg_match('/^[A-Z]{2}$/', $value) === 1) {
                return $value;
            }
        }

        return null;
    }
}
