<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Service\RichText\RichHtmlSanitizer;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves CV professional experience entries from persisted JSON with placeholder rows when unset.
 */
class CvExperienceSettingsService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RichHtmlSanitizer $richHtmlSanitizer,
    ) {
    }

    /**
     * @brief Resolve experience data for admin forms and public CV rendering.
     *
     * @param string $contentJson CvProfile JSON payload.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer or request locale.
     * @return array{
     *     entriesByLocale: array<string, list<array<string, mixed>>>,
     *     entries: list<array<string, mixed>>,
     *     entriesFull: list<array<string, mixed>>,
     *     hasSecondaryVisible: bool,
     *     hasPersistedMap: bool
     * }
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function resolveFromContentJson(
        string $contentJson,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale
    ): array {
        return $this->resolveFromPayload(
            $this->decodeJsonPayload($contentJson),
            $activeLocales,
            $defaultLocale,
            $displayLocale
        );
    }

    /**
     * @brief Resolve experience data from a decoded CvProfile payload (avoids lossy JSON round-trips).
     *
     * @param array<string, mixed> $payload Decoded CvProfile JSON.
     * @param list<string> $activeLocales Site active locales.
     * @param string $defaultLocale Site default locale.
     * @param string $displayLocale Viewer or request locale.
     * @return array{
     *     entriesByLocale: array<string, list<array<string, mixed>>>,
     *     entries: list<array<string, mixed>>,
     *     entriesFull: list<array<string, mixed>>,
     *     hasSecondaryVisible: bool,
     *     hasPersistedMap: bool
     * }
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function resolveFromPayload(
        array $payload,
        array $activeLocales,
        string $defaultLocale,
        string $displayLocale
    ): array {
        $hasPersistedMap = ExperienceContract::hasPersistedExperienceMap($payload);
        $stored = ExperienceContract::entriesByLocaleFromStoredPayload($payload);

        $entriesByLocale = [];
        foreach ($activeLocales as $locale) {
            $localeEntries = $stored[$locale] ?? [];
            if ($localeEntries === [] && !$hasPersistedMap) {
                $localeEntries = $this->buildPlaceholderEntriesForLocale($locale);
            }

            $entriesByLocale[$locale] = $localeEntries;
        }

        if ($hasPersistedMap) {
            $entriesByLocale = ExperienceContract::alignEntriesAcrossActiveLocales($entriesByLocale, $activeLocales);
            $entriesByLocale = ExperienceContract::syncIsPrimaryAcrossLocales($entriesByLocale);
        }

        foreach ($entriesByLocale as $locale => $localeEntries) {
            $entriesByLocale[$locale] = $this->sanitizeEntriesDetailHtml($this->attachPeriodLabels($localeEntries, $locale));
        }

        $displayLocaleKey = $this->resolveDisplayLocaleKey($entriesByLocale, $displayLocale, $defaultLocale, $activeLocales);
        $entries = $this->normalizeDisplayEntries(
            $entriesByLocale[$displayLocaleKey] ?? [],
            $displayLocaleKey,
            $defaultLocale,
            $entriesByLocale
        );

        return [
            'entriesByLocale' => $entriesByLocale,
            'entries' => $entries,
            'entriesFull' => $this->resolveAll($entries),
            'hasSecondaryVisible' => $this->hasSecondaryVisible($entries),
            'hasPersistedMap' => $hasPersistedMap,
        ];
    }

    /**
     * @brief Coerce a display-locale entry list, deduplicating shared ids across accidental multi-locale merges.
     *
     * @param mixed $entries Resolved rows for one locale or a mistaken locale map.
     * @param string $displayLocale Preferred viewer locale.
     * @param string $defaultLocale Site default locale.
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Full locale map used as fallback source.
     * @return list<array<string, mixed>>
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function normalizeDisplayEntries(
        mixed $entries,
        string $displayLocale,
        string $defaultLocale,
        array $entriesByLocale = []
    ): array {
        if (!is_array($entries)) {
            return [];
        }

        if ($this->isEntriesByLocaleMap($entries)) {
            $entries = $entriesByLocale[$displayLocale]
                ?? $entriesByLocale[$defaultLocale]
                ?? (is_array(reset($entries)) ? reset($entries) : []);
        }

        if (!is_array($entries)) {
            return [];
        }

        $normalized = [];
        $seenIds = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryId = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
            if ($entryId !== '') {
                if (isset($seenIds[$entryId])) {
                    continue;
                }

                $seenIds[$entryId] = true;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @brief Whether a value looks like an `entriesByLocale` map rather than a flat entry list.
     *
     * @param array<mixed, mixed> $entries Candidate list or locale map.
     * @return bool
     * @date 2026-06-08
     * @author Stephane H.
     */
    private function isEntriesByLocaleMap(array $entries): bool
    {
        if ($entries === [] || array_is_list($entries)) {
            return false;
        }

        foreach ($entries as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z]{2}$/', $key) || !is_array($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @brief Filter entries for main CV timeline (primary and visible).
     *
     * @param list<array<string, mixed>> $entries Resolved entries with periodLabel.
     * @return list<array<string, mixed>>
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function filterPrimaryVisible(array $entries): array
    {
        $filtered = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => ($entry['isPrimary'] ?? true) === true
        ));

        return $this->sortEntries($filtered);
    }

    /**
     * @brief Filter entries for full experience page (all published rows).
     *
     * @param list<array<string, mixed>> $entries Resolved entries with periodLabel.
     * @return list<array<string, mixed>>
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function filterAllVisible(array $entries): array
    {
        return $this->sortEntries(array_values($entries));
    }

    /**
     * @brief Resolve all visible entries for the full experience page, marking rows hidden on the primary CV timeline.
     *
     * @param list<array<string, mixed>> $entries Resolved entries with periodLabel.
     * @return list<array<string, mixed>>
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function resolveAll(array $entries): array
    {
        $visible = $this->filterAllVisible($entries);

        return array_map(static function (array $entry): array {
            $entry['hiddenOnPrimary'] = ($entry['isPrimary'] ?? true) !== true;

            return $entry;
        }, $visible);
    }

    /**
     * @brief Whether at least one visible secondary entry exists.
     *
     * @param list<array<string, mixed>> $entries Resolved entries.
     * @return bool
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function hasSecondaryVisible(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (($entry['isPrimary'] ?? true) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @brief Build per-locale admin preview payloads (primary visible timeline + secondary flag).
     *
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Resolved entries with periodLabel per locale.
     * @return array<string, array{entries: list<array<string, mixed>>, hasSecondaryVisible: bool}>
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function buildAdminPreviewPayloadByLocale(array $entriesByLocale): array
    {
        $previewByLocale = [];
        foreach ($entriesByLocale as $locale => $localeEntries) {
            if (!is_string($locale) || !is_array($localeEntries)) {
                continue;
            }

            $previewByLocale[$locale] = [
                'entries' => $this->filterPrimaryVisible($localeEntries),
                'hasSecondaryVisible' => $this->hasSecondaryVisible($localeEntries),
            ];
        }

        return $previewByLocale;
    }

    /**
     * @brief Build period label for one entry.
     *
     * @param array<string, mixed> $entry Normalized entry.
     * @param string $locale Locale for translation.
     * @return string
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function buildPeriodLabel(array $entry, string $locale): string
    {
        $start = ExperienceContract::formatYearMonthForDisplay((string) ($entry['startDate'] ?? ''));
        if (($entry['isCurrent'] ?? false) === true) {
            return $this->translator->trans(
                'cv.experience.period_current',
                ['%start%' => $start],
                'messages',
                $locale
            );
        }

        $end = ExperienceContract::formatYearMonthForDisplay((string) ($entry['endDate'] ?? ''));

        return $this->translator->trans(
            'cv.experience.period_range',
            ['%start%' => $start, '%end%' => $end],
            'messages',
            $locale
        );
    }

    /**
     * @param string $json JSON payload.
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param list<array<string, mixed>> $entries Entries.
     * @param string $locale Locale code.
     * @return list<array<string, mixed>>
     */
    private function attachPeriodLabels(array $entries, string $locale): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $entry['periodLabel'] = $this->buildPeriodLabel($entry, $locale);
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $entriesByLocale Map by locale.
     * @param string $displayLocale Preferred locale.
     * @param string $defaultLocale Default locale.
     * @param list<string> $activeLocales Active locales order.
     * @return string
     */
    private function resolveDisplayLocaleKey(
        array $entriesByLocale,
        string $displayLocale,
        string $defaultLocale,
        array $activeLocales
    ): string {
        if (($entriesByLocale[$displayLocale] ?? []) !== []) {
            return $displayLocale;
        }

        if (($entriesByLocale[$defaultLocale] ?? []) !== []) {
            return $defaultLocale;
        }

        foreach ($activeLocales as $loc) {
            if (($entriesByLocale[$loc] ?? []) !== []) {
                return $loc;
            }
        }

        return $displayLocale;
    }

    /**
     * @param list<array<string, mixed>> $entries Entries.
     * @return list<array<string, mixed>>
     */
    private function sortEntries(array $entries): array
    {
        $sorted = $entries;
        usort(
            $sorted,
            static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0)
        );

        return $sorted;
    }

    /**
     * @brief Build a single visible placeholder experience row inviting admin completion.
     *
     * @param string $locale Locale code for translated labels.
     * @return list<array<string, mixed>>
     * @date 2026-05-17
     * @author Stephane H.
     */
    private function buildPlaceholderEntriesForLocale(string $locale): array
    {
        $row = [
            'id' => ExperienceContract::generateDeterministicUuid('placeholder-experience-'.$locale),
            'sortOrder' => 0,
            'startDate' => '2000-01',
            'endDate' => '2000-12',
            'isCurrent' => false,
            'title' => $this->translator->trans('cv.placeholder.experience.title', [], 'messages', $locale),
            'companyName' => $this->translator->trans('cv.placeholder.experience.company', [], 'messages', $locale),
            'companyWebsiteUrl' => '',
            'companyLogoPath' => null,
            'hideCompanyName' => false,
            'highlights' => [
                $this->translator->trans('cv.placeholder.experience.description', [], 'messages', $locale),
            ],
            'isPrimary' => true,
        ];

        $normalized = ExperienceContract::normalizeEntry($row, 0);
        if ($normalized === null) {
            return [];
        }

        $normalized['periodLabel'] = $this->translator->trans('cv.placeholder.experience.period', [], 'messages', $locale);

        return [$normalized];
    }

    /**
     * @brief Re-sanitize stored detail HTML on read for legacy or out-of-band rows.
     *
     * @param list<array<string, mixed>> $entries Resolved entries for one locale.
     * @return list<array<string, mixed>>
     * @date 2026-06-03
     * @author Stephane H.
     */
    private function sanitizeEntriesDetailHtml(array $entries): array
    {
        $sanitized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rawHtml = $entry['detailHtml'] ?? '';
            if (is_string($rawHtml) && $rawHtml !== '') {
                $entry['detailHtml'] = $this->richHtmlSanitizer->sanitize($rawHtml);
            } else {
                $entry['detailHtml'] = '';
            }

            $sanitized[] = $entry;
        }

        return $sanitized;
    }
}
