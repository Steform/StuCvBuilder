<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * Validates safe redirect targets for CV access gate.
 */
class CvAccessTargetResolver
{
    /**
     * @brief Resolve and sanitize post-gate redirect target path.
     *
     * @param string|null $target Raw target query value.
     * @return string Safe path starting with /cv/ or default /cv/.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function resolveSafeTarget(?string $target): string
    {
        $candidate = trim((string) $target);
        if ($candidate === '') {
            return '/cv/';
        }

        if (!str_starts_with($candidate, '/cv/') && $candidate !== '/cv') {
            return '/cv/';
        }

        if (str_contains($candidate, '//') || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
            return '/cv/';
        }

        if ($candidate === '/cv') {
            return '/cv/';
        }

        return $candidate;
    }

    /**
     * @brief Build target query preserving format when present (without score).
     *
     * @param string $safePath Safe internal path.
     * @param string $formatCode Resolved format code.
     * @return string Path with optional query string.
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function buildRedirectUrl(string $safePath, string $formatCode): string
    {
        if ($formatCode === '') {
            return $safePath;
        }

        $separator = str_contains($safePath, '?') ? '&' : '?';

        return $safePath.$separator.'format='.rawurlencode($formatCode);
    }
}
