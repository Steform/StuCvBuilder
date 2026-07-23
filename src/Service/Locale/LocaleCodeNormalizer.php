<?php

declare(strict_types=1);

namespace App\Service\Locale;

/**
 * @brief Canonical site locale codes with legacy Norwegian macro-language aliases.
 *
 * @date 2026-07-05
 * @author Stephane H.
 */
final class LocaleCodeNormalizer
{
    public const CANONICAL_BOKMAL = 'nb';

    public const LEGACY_NORWEGIAN = 'no';

    /**
     * @brief Normalize a raw locale string to the canonical two-letter site code when recognized.
     *
     * Maps legacy `no`, browser `nb`, and unsupported UI `nn` to canonical bokmål `nb`.
     *
     * @param string $locale Raw locale from request, cookie, path, or storage.
     * @return string|null Canonical two-letter code, or null when empty.
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function normalizeRawCode(string $locale): ?string
    {
        $normalized = substr(strtolower(trim(str_replace('_', '-', $locale))), 0, 2);
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, [self::LEGACY_NORWEGIAN, self::CANONICAL_BOKMAL, 'nn'], true)) {
            return self::CANONICAL_BOKMAL;
        }

        return $normalized;
    }

    /**
     * @brief Normalize a locale and ensure it is allowed in the provided supported list.
     *
     * @param string $locale Raw locale value.
     * @param list<string> $allowedLocales Supported canonical locale codes.
     * @return string|null Canonical allowed locale, or null when unsupported.
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function normalizeToSupported(string $locale, array $allowedLocales): ?string
    {
        $normalized = $this->normalizeRawCode($locale);
        if ($normalized === null) {
            return null;
        }

        return in_array($normalized, $allowedLocales, true) ? $normalized : null;
    }

    /**
     * @brief Resolve a URL path prefix candidate, including legacy `no`, to a supported locale.
     *
     * @param string $pathPrefix Two-letter path prefix from the request URI.
     * @param list<string> $allowedLocales Supported canonical locale codes.
     * @return string|null Supported locale code, or null when the prefix is not a locale.
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function normalizePathPrefix(string $pathPrefix, array $allowedLocales): ?string
    {
        return $this->normalizeToSupported($pathPrefix, $allowedLocales);
    }
}
