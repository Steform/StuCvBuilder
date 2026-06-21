<?php



declare(strict_types=1);



namespace App\Service\Cv;



use Symfony\Contracts\Translation\TranslatorInterface;



/**

 * Resolves CV certification entries from persisted JSON with generic placeholder defaults and display filters.

 */

class CvCertificationSettingsService

{

    public function __construct(

        private readonly TranslatorInterface $translator,

    ) {

    }



    /**

     * @brief Resolve certification data for admin forms and public CV rendering.

     *

     * @param string $contentJson CvProfile JSON payload.

     * @param list<string> $activeLocales Site active locales.

     * @param string $defaultLocale Site default locale.

     * @param string $displayLocale Viewer or request locale.

     * @return array{

     *     canonicalEntries: list<array<string, mixed>>,

     *     entries: list<array<string, mixed>>,

     *     entriesFull: list<array<string, mixed>>,

     *     hasSecondaryVisible: bool,

     *     hasPersistedEntries: bool

     * }

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function resolveFromContentJson(

        string $contentJson,

        array $activeLocales,

        string $defaultLocale,

        string $displayLocale

    ): array {

        $payload = $this->decodeJsonPayload($contentJson);



        return $this->resolveFromPayload($payload, $activeLocales, $defaultLocale, $displayLocale);

    }



    /**

     * @brief Resolve certification data from decoded payload array.

     *

     * @param array<string, mixed> $payload Decoded profile JSON.

     * @param list<string> $activeLocales Site active locales.

     * @param string $defaultLocale Site default locale.

     * @param string $displayLocale Viewer or request locale.

     * @return array{

     *     canonicalEntries: list<array<string, mixed>>,

     *     entries: list<array<string, mixed>>,

     *     entriesFull: list<array<string, mixed>>,

     *     hasSecondaryVisible: bool,

     *     hasPersistedEntries: bool

     * }

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function resolveFromPayload(

        array $payload,

        array $activeLocales,

        string $defaultLocale,

        string $displayLocale,

    ): array {

        $hasPersistedEntries = CertificationContract::hasPersistedEntries($payload);

        $canonicalEntries = CertificationContract::entriesFromStoredPayload($payload, $activeLocales, $defaultLocale);



        if ($canonicalEntries === [] && !$hasPersistedEntries) {

            $canonicalEntries = $this->buildPlaceholderCanonicalEntries($activeLocales, $defaultLocale);

        }



        $entries = $this->projectEntriesForLocale($canonicalEntries, $displayLocale, $defaultLocale, $activeLocales);

        $entries = $this->attachPeriodLabels($entries, $displayLocale);



        return [

            'canonicalEntries' => $canonicalEntries,

            'entries' => $entries,

            'entriesFull' => $this->resolveAll($entries),

            'hasSecondaryVisible' => $this->hasSecondaryVisible($entries),

            'hasPersistedEntries' => $hasPersistedEntries,

        ];

    }



    /**

     * @brief Project canonical entries to one display locale for public templates.

     *

     * @param list<array<string, mixed>> $canonicalEntries Stored entries with localized maps.

     * @param string $displayLocale Viewer locale.

     * @param string $defaultLocale Site default locale.

     * @param list<string> $activeLocales Active locales.

     * @return list<array<string, mixed>>

     * @date 2026-06-11

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

            if (!is_array($entry)) {

                continue;

            }



            $titleByLocale = is_array($entry['titleByLocale'] ?? null) ? $entry['titleByLocale'] : [];

            $providerByLocale = is_array($entry['providerNameByLocale'] ?? null) ? $entry['providerNameByLocale'] : [];

            $locationByLocale = is_array($entry['locationByLocale'] ?? null) ? $entry['locationByLocale'] : [];

            $highlightsByLocale = is_array($entry['highlightsByLocale'] ?? null) ? $entry['highlightsByLocale'] : [];



            $title = $this->resolveLocalizedValue($titleByLocale, $displayLocale, $defaultLocale, $activeLocales);

            if ($title === '') {

                continue;

            }



            $projected[] = [

                'id' => $entry['id'] ?? '',

                'sortOrder' => $entry['sortOrder'] ?? 0,

                'startDate' => $entry['startDate'] ?? '',

                'endDate' => $entry['endDate'] ?? null,

                'isCurrent' => ($entry['isCurrent'] ?? false) === true,

                'title' => $title,

                'providerName' => $this->resolveLocalizedValue($providerByLocale, $displayLocale, $defaultLocale, $activeLocales),

                'providerWebsiteUrl' => $entry['providerWebsiteUrl'] ?? null,

                'location' => $this->resolveLocalizedValue($locationByLocale, $displayLocale, $defaultLocale, $activeLocales),

                'proofPdfPath' => $entry['proofPdfPath'] ?? null,

                'proofUrl' => $entry['proofUrl'] ?? null,

                'highlights' => $this->resolveHighlightsForLocale($highlightsByLocale, $displayLocale, $defaultLocale, $activeLocales),

                'isPrimary' => ($entry['isPrimary'] ?? true) === true,

            ];

        }



        return $this->sortEntries($projected);

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

     * @brief Filter entries for full certification page (all published rows).

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

     * @brief Resolve all visible entries for the full certification page, marking rows hidden on the primary CV timeline.

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

     * @param list<array<string, mixed>> $canonicalEntries Canonical stored entries.

     * @param list<string> $activeLocales Site active locales.

     * @param string $defaultLocale Site default locale.

     * @return array<string, array{entries: list<array<string, mixed>>, hasSecondaryVisible: bool}>

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function buildAdminPreviewPayloadByLocale(

        array $canonicalEntries,

        array $activeLocales,

        string $defaultLocale,

    ): array {

        $previewByLocale = [];

        foreach ($activeLocales as $locale) {

            $localeEntries = $this->attachPeriodLabels(

                $this->projectEntriesForLocale($canonicalEntries, $locale, $defaultLocale, $activeLocales),

                $locale

            );

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

        $start = CertificationContract::formatYearMonthForDisplay((string) ($entry['startDate'] ?? ''));

        if (($entry['isCurrent'] ?? false) === true) {

            return $this->translator->trans(

                'cv.certification.period_current',

                ['%start%' => $start],

                'messages',

                $locale

            );

        }



        $end = CertificationContract::formatYearMonthForDisplay((string) ($entry['endDate'] ?? ''));



        return $this->translator->trans(

            'cv.certification.period_range',

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

     * @param list<array<string, mixed>> $entries Entries.

     * @return list<array<string, mixed>>

     */

    private function sortEntries(array $entries): array

    {

        $sorted = $entries;

        usort($sorted, static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));



        return $sorted;

    }



    /**

     * @brief Resolve one localized string with fallback chain.

     *

     * @param array<string, string> $valueByLocale Localized values.

     * @param string $displayLocale Preferred locale.

     * @param string $defaultLocale Site default locale.

     * @param list<string> $activeLocales Active locales.

     * @return string

     * @date 2026-06-11

     * @author Stephane H.

     */

    private function resolveLocalizedValue(

        array $valueByLocale,

        string $displayLocale,

        string $defaultLocale,

        array $activeLocales,

    ): string {

        if (($valueByLocale[$displayLocale] ?? '') !== '') {

            return $valueByLocale[$displayLocale];

        }



        if (($valueByLocale[$defaultLocale] ?? '') !== '') {

            return $valueByLocale[$defaultLocale];

        }



        foreach ($activeLocales as $locale) {

            if (($valueByLocale[$locale] ?? '') !== '') {

                return $valueByLocale[$locale];

            }

        }



        return '';

    }



    /**

     * @brief Resolve highlights for one display locale with fallback chain.

     *

     * @param array<string, list<string>> $highlightsByLocale Localized highlight lists.

     * @param string $displayLocale Preferred locale.

     * @param string $defaultLocale Site default locale.

     * @param list<string> $activeLocales Active locales.

     * @return list<string>

     * @date 2026-06-11

     * @author Stephane H.

     */

    private function resolveHighlightsForLocale(

        array $highlightsByLocale,

        string $displayLocale,

        string $defaultLocale,

        array $activeLocales,

    ): array {

        if (($highlightsByLocale[$displayLocale] ?? []) !== []) {

            return $highlightsByLocale[$displayLocale];

        }



        if (($highlightsByLocale[$defaultLocale] ?? []) !== []) {

            return $highlightsByLocale[$defaultLocale];

        }



        foreach ($activeLocales as $locale) {

            if (($highlightsByLocale[$locale] ?? []) !== []) {

                return $highlightsByLocale[$locale];

            }

        }



        return [];

    }



    /**

     * @brief Build a single visible placeholder certification row inviting admin completion.

     *

     * @param list<string> $activeLocales Site active locales.

     * @param string $defaultLocale Site default locale.

     * @return list<array<string, mixed>>

     * @date 2026-06-11

     * @author Stephane H.

     */

    private function buildPlaceholderCanonicalEntries(array $activeLocales, string $defaultLocale): array

    {

        $titleByLocale = [];

        $providerByLocale = [];

        $highlightsByLocale = [];

        foreach ($activeLocales as $locale) {

            $titleByLocale[$locale] = $this->translator->trans('cv.placeholder.certification.title', [], 'messages', $locale);

            $providerByLocale[$locale] = $this->translator->trans('cv.placeholder.certification.provider', [], 'messages', $locale);

            $highlightsByLocale[$locale] = [

                $this->translator->trans('cv.placeholder.certification.description', [], 'messages', $locale),

            ];

        }



        $entry = CertificationContract::normalizeEntry(

            [

                'id' => CertificationContract::generateDeterministicUuid('placeholder-certification'),

                'sortOrder' => 0,

                'startDate' => '2000-01',

                'endDate' => '2000-12',

                'isCurrent' => false,

                'titleByLocale' => $titleByLocale,

                'providerNameByLocale' => $providerByLocale,

                'highlightsByLocale' => $highlightsByLocale,

                'isPrimary' => true,

            ],

            0,

            $activeLocales,

            $defaultLocale,

        );



        return $entry !== null ? [$entry] : [];

    }

}


