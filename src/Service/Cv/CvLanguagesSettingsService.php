<?php



declare(strict_types=1);



namespace App\Service\Cv;



use Symfony\Contracts\Translation\TranslatorInterface;



/**

 * @brief Resolves CV language entries from persisted JSON for admin and public rendering.

 *

 * @date 2026-06-10

 * @author Stephane H.

 */

class CvLanguagesSettingsService

{

    public function __construct(

        private readonly TranslatorInterface $translator,

    ) {

    }



    /**

     * @brief Resolve language data from content JSON or payload array.

     *

     * @param string $contentJson CvProfile JSON payload.

     * @param list<string> $activeLocales Site active locales.

     * @param string $defaultLocale Site default locale.

     * @param string $displayLocale Viewer locale for translated labels.

     * @return array{

     *     canonicalEntries: list<array<string, mixed>>,

     *     entries: list<array<string, mixed>>,

     *     hasPersistedEntries: bool

     * }

     * @date 2026-06-10

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

     * @brief Resolve language data from decoded payload array.

     *

     * @param array<string, mixed> $payload Decoded profile JSON.

     * @param list<string> $activeLocales Site active locales.

     * @param string $defaultLocale Site default locale.

     * @param string $displayLocale Viewer locale for translated labels.

     * @return array{

     *     canonicalEntries: list<array<string, mixed>>,

     *     entries: list<array<string, mixed>>,

     *     hasPersistedEntries: bool

     * }

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function resolveFromPayload(

        array $payload,

        array $activeLocales,

        string $defaultLocale,

        string $displayLocale,

    ): array {

        $hasPersistedEntries = LanguagesContract::hasPersistedEntries($payload);

        $rawEntries = is_array($payload[LanguagesContract::KEY_ENTRIES] ?? null)

            ? array_values($payload[LanguagesContract::KEY_ENTRIES])

            : [];

        $canonicalEntries = [];

        $sortOrder = 0;

        foreach ($rawEntries as $row) {

            if (!is_array($row)) {

                continue;

            }



            $migrated = $this->migrateLegacyEntry($row, $activeLocales, $defaultLocale);

            $entry = LanguagesContract::normalizeEntry($migrated, $sortOrder, $activeLocales, $defaultLocale);

            if ($entry === null) {

                continue;

            }



            $canonicalEntries[] = $this->attachAdminLabels($entry, $displayLocale, $defaultLocale, $activeLocales);

            ++$sortOrder;

        }



        return [

            'canonicalEntries' => $canonicalEntries,

            'entries' => $this->projectEntriesForLocale($canonicalEntries, $displayLocale, $defaultLocale, $activeLocales),

            'hasPersistedEntries' => $hasPersistedEntries,

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

     * @date 2026-06-10

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

            $languageLabel = $this->resolveLabelForLocale($labelByLocale, $displayLocale, $defaultLocale, $activeLocales);

            if ($languageLabel === '') {

                continue;

            }



            $levelCode = is_string($entry['levelCode'] ?? null) ? $entry['levelCode'] : '';

            $projected[] = [

                'id' => $entry['id'] ?? '',

                'languageLabel' => $languageLabel,

                'levelCode' => $levelCode,

                'levelLabel' => $this->translator->trans(

                    'cv.languages.level.'.$levelCode,

                    [],

                    'messages',

                    $displayLocale

                ),

                'levelProgressPercent' => $entry['levelProgressPercent'] ?? 0,

                'notes' => $entry['notes'] ?? '',

                'sortOrder' => $entry['sortOrder'] ?? 0,

            ];

        }



        usort($projected, static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));



        return $projected;

    }



    /**

     * @brief Upgrade legacy language code rows with translated labels for each active locale.

     *

     * @param array<string, mixed> $row Stored row.

     * @param list<string> $activeLocales Site active locales.

     * @param string $defaultLocale Site default locale.

     * @return array<string, mixed>

     * @date 2026-06-10

     * @author Stephane H.

     */

    private function migrateLegacyEntry(array $row, array $activeLocales, string $defaultLocale): array

    {

        if (is_array($row['labelByLocale'] ?? null) && $row['labelByLocale'] !== []) {

            return $row;

        }



        $languageCode = is_string($row['languageCode'] ?? null) ? strtolower(trim($row['languageCode'])) : '';

        if ($languageCode === '') {

            return $row;

        }



        $labelByLocale = [];

        $fallbackLocales = $activeLocales !== [] ? $activeLocales : [$defaultLocale];

        foreach ($fallbackLocales as $locale) {

            $translated = $this->translator->trans(

                'cv.languages.code.'.$languageCode,

                [],

                'messages',

                $locale

            );

            $labelByLocale[$locale] = $translated !== 'cv.languages.code.'.$languageCode

                ? $translated

                : ucfirst($languageCode);

        }



        $row['labelByLocale'] = $labelByLocale;

        unset($row['languageCode']);



        return $row;

    }



    /**

     * @brief Attach display labels used by admin accordion summaries.

     *

     * @param array<string, mixed> $entry Canonical entry.

     * @param string $displayLocale Admin UI locale.

     * @param string $defaultLocale Site default locale.

     * @param list<string> $activeLocales Active locales.

     * @return array<string, mixed>

     * @date 2026-06-10

     * @author Stephane H.

     */

    private function attachAdminLabels(

        array $entry,

        string $displayLocale,

        string $defaultLocale,

        array $activeLocales,

    ): array {

        $labelByLocale = is_array($entry['labelByLocale'] ?? null) ? $entry['labelByLocale'] : [];

        $levelCode = is_string($entry['levelCode'] ?? null) ? $entry['levelCode'] : '';

        $entry['languageLabel'] = $this->resolveLabelForLocale($labelByLocale, $displayLocale, $defaultLocale, $activeLocales);

        $entry['levelLabel'] = $levelCode !== ''

            ? $this->translator->trans('cv.languages.level.'.$levelCode, [], 'messages', $displayLocale)

            : '';



        return $entry;

    }



    /**

     * @brief Resolve one localized label with fallback chain.

     *

     * @param array<string, string> $labelByLocale Localized labels.

     * @param string $displayLocale Preferred locale.

     * @param string $defaultLocale Site default locale.

     * @param list<string> $activeLocales Active locales.

     * @return string

     * @date 2026-06-10

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

