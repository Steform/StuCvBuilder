<?php

declare(strict_types=1);

namespace App\Service\Cv;

/**
 * @brief Resolves CV interest entries from persisted JSON for admin and public rendering.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
class CvInterestsSettingsService
{
    /**
     * @brief Resolve interest data from content JSON.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer locale.
     * @return array{
     *     canonicalEntries: list<array<string, mixed>>,
     *     entries: list<array<string, mixed>>,
     *     hasPersistedEntries: bool,
     *     columnsPerRow: int,
     *     columnsPerRowSmall: int
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
     * @brief Resolve interest data from decoded payload array.
     *
     * @param array<string, mixed> $payload Decoded profile JSON.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer locale.
     * @return array{
     *     canonicalEntries: list<array<string, mixed>>,
     *     entries: list<array<string, mixed>>,
     *     hasPersistedEntries: bool,
     *     columnsPerRow: int,
     *     columnsPerRowSmall: int
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
        $hasPersistedEntries = InterestsContract::hasPersistedEntries($payload);
        $canonicalEntries = InterestsContract::entriesFromStoredPayload($payload, $activeLocales, $defaultLocale);

        return [
            'canonicalEntries' => $canonicalEntries,
            'entries' => $this->projectEntriesForLocale($canonicalEntries, $displayLocale, $defaultLocale, $activeLocales),
            'hasPersistedEntries' => $hasPersistedEntries,
            'columnsPerRow' => InterestsContract::columnsPerRowFromPayload($payload),
            'columnsPerRowSmall' => InterestsContract::columnsPerRowSmallFromPayload($payload),
        ];
    }

    /**
     * @brief Project canonical entries to one display locale for public templates.
     *
     * @param list<array<string, mixed>> $canonicalEntries Stored entries with labelByLocale.
     * @param string $displayLocale Viewer locale.
     * @param string $defaultLocale Site default locale.
     * @param list<string> $activeLocales Active locales.
     * @return list<array<string, mixed>>
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function projectEntriesForLocale(
        array $canonicalEntries,
        string $displayLocale,
        string $defaultLocale,
        array $activeLocales,
    ): array {
        $projected = [];
        foreach ($canonicalEntries as $entry) {
            $labelByLocale = is_array($entry['labelByLocale'] ?? null) ? $entry['labelByLocale'] : [];
            $label = $this->resolveLabelForLocale($labelByLocale, $displayLocale, $defaultLocale, $activeLocales);
            if ($label === '') {
                continue;
            }

            $projected[] = [
                'id' => $entry['id'] ?? '',
                'iconType' => $entry['iconType'] ?? InterestsContract::ICON_TYPE_BOOTSTRAP,
                'icon' => $entry['icon'] ?? '',
                'iconPath' => $entry['iconPath'] ?? null,
                'label' => $label,
                'sortOrder' => $entry['sortOrder'] ?? 0,
            ];
        }

        usort($projected, static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

        return $projected;
    }

    /**
     * @brief Resolve one localized label with fallback chain.
     *
     * @param array<string, string> $labelByLocale Localized labels.
     * @param string $displayLocale Preferred locale.
     * @param string $defaultLocale Site default locale.
     * @param list<string> $activeLocales Active locales.
     * @return string
     * @date 2026-06-09
     * @author Stephane H.
     */
    private function resolveLabelForLocale(
        array $labelByLocale,
        string $displayLocale,
        string $defaultLocale,
        array $activeLocales,
    ): string {
        if (($labelByLocale[$displayLocale] ?? '') !== '') {
            return $labelByLocale[$displayLocale];
        }

        if (($labelByLocale[$defaultLocale] ?? '') !== '') {
            return $labelByLocale[$defaultLocale];
        }

        foreach ($activeLocales as $locale) {
            if (($labelByLocale[$locale] ?? '') !== '') {
                return $labelByLocale[$locale];
            }
        }

        return '';
    }
}
