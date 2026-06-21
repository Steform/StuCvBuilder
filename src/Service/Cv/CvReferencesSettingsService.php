<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * @brief Resolves CV reference entries from persisted JSON for admin and public rendering.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
class CvReferencesSettingsService
{
    /**
     * @brief Resolve reference data from content JSON.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer locale.
     * @return array{
     *     entriesByLocale: array<string, list<array<string, mixed>>>,
     *     entries: list<array<string, mixed>>,
     *     hasPersistedMap: bool,
     *     sectionEnabled: bool
     * }
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resolveFromContentJson(
        string $contentJson,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale,
    ): array {
        $payload = json_decode($contentJson, true);

        return $this->resolveFromPayload(
            is_array($payload) ? $payload : [],
            $activeLocales,
            $defaultLocale,
            $displayLocale,
        );
    }

    /**
     * @brief Resolve reference data from decoded payload array.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer locale.
     * @return array{
     *     entriesByLocale: array<string, list<array<string, mixed>>>,
     *     entries: list<array<string, mixed>>,
     *     hasPersistedMap: bool,
     *     sectionEnabled: bool
     * }
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function resolveFromPayload(
        array $payload,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale,
    ): array {
        $hasPersistedMap = ReferencesContract::hasPersistedMap($payload);
        $stored = ReferencesContract::entriesByLocaleFromStoredPayload($payload);

        $entriesByLocale = [];
        foreach ($activeLocales as $locale) {
            $entriesByLocale[$locale] = $stored[$locale] ?? [];
        }

        $displayKey = $this->resolveDisplayLocaleKey($entriesByLocale, $displayLocale, $defaultLocale, $activeLocales);

        return [
            'entriesByLocale' => $entriesByLocale,
            'entries' => $entriesByLocale[$displayKey] ?? [],
            'hasPersistedMap' => $hasPersistedMap,
            'sectionEnabled' => ReferencesContract::isSectionEnabledFromPayload($payload),
        ];
    }

    /**
     * @brief Pick the best locale key for public projection.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale map.
     * @param string $displayLocale Preferred locale.
     * @param string $defaultLocale Site default locale.
     * @param list<string> $activeLocales Active locales.
     * @return string
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function resolveDisplayLocaleKey(
        array $entriesByLocale,
        string $displayLocale,
        string $defaultLocale,
        array $activeLocales,
    ): string {
        if (($entriesByLocale[$displayLocale] ?? []) !== []) {
            return $displayLocale;
        }

        if (($entriesByLocale[$defaultLocale] ?? []) !== []) {
            return $defaultLocale;
        }

        foreach ($activeLocales as $locale) {
            if (($entriesByLocale[$locale] ?? []) !== []) {
                return $locale;
            }
        }

        return $displayLocale;
    }

    /**
     * @brief Build per-locale admin preview payload for references section partials.
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Locale map.
     * @param bool $sectionEnabled Whether the references section is visible publicly.
     * @return array<string, array{entries: list<array<string, mixed>>, sectionEnabled: bool}>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function buildAdminPreviewPayloadByLocale(array $entriesByLocale, bool $sectionEnabled): array
    {
        $previewByLocale = [];
        foreach ($entriesByLocale as $locale => $localeEntries) {
            if (!is_string($locale) || !is_array($localeEntries)) {
                continue;
            }

            $previewByLocale[$locale] = [
                'entries' => $localeEntries,
                'sectionEnabled' => $sectionEnabled,
            ];
        }

        return $previewByLocale;
    }
}
