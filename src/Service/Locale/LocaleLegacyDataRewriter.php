<?php

declare(strict_types=1);

namespace App\Service\Locale;

/**
 * @brief Rewrite persisted legacy `no` site locale keys and values to canonical `nb`.
 *
 * @date 2026-07-05
 * @author Stephane H.
 */
final class LocaleLegacyDataRewriter
{
    /**
     * @brief Rewrite a scalar locale value when it stores the legacy Norwegian site code.
     *
     * @param mixed $value Raw scalar or structured value.
     * @return mixed Updated value.
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function rewriteScalarLocale(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return strtolower(trim($value)) === LocaleCodeNormalizer::LEGACY_NORWEGIAN
            ? LocaleCodeNormalizer::CANONICAL_BOKMAL
            : $value;
    }

    /**
     * @brief Recursively rewrite associative array keys and scalar locale values.
     *
     * @param mixed $value JSON-decoded structure.
     * @return mixed Structure with legacy site locale `no` migrated to `nb`.
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function rewriteStructure(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $this->rewriteScalarLocale($value);
        }

        $rewritten = [];
        foreach ($value as $key => $child) {
            $newKey = is_string($key) && $key === LocaleCodeNormalizer::LEGACY_NORWEGIAN
                ? LocaleCodeNormalizer::CANONICAL_BOKMAL
                : $key;
            $rewritten[$newKey] = $this->rewriteStructure($child);
        }

        return $rewritten;
    }

    /**
     * @brief Rewrite active locale configuration payload.
     *
     * @param array<string, mixed> $localeData Locale configuration JSON payload.
     * @return array{active_locales: list<string>, default_locale: string}
     * @date 2026-07-05
     * @author Stephane H.
     */
    public function rewriteLocaleConfiguration(array $localeData): array
    {
        $active = is_array($localeData['active_locales'] ?? null) ? $localeData['active_locales'] : [];
        $activeLocales = [];
        foreach ($active as $locale) {
            if (!is_string($locale)) {
                continue;
            }

            $rewritten = $this->rewriteScalarLocale($locale);
            if (!is_string($rewritten) || $rewritten === '' || in_array($rewritten, $activeLocales, true)) {
                continue;
            }

            $activeLocales[] = $rewritten;
        }

        $defaultLocale = is_string($localeData['default_locale'] ?? null)
            ? (string) $this->rewriteScalarLocale($localeData['default_locale'])
            : '';

        return [
            'active_locales' => $activeLocales,
            'default_locale' => $defaultLocale,
        ];
    }
}
