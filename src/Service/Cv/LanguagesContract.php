<?php



declare(strict_types=1);



namespace App\Service\Cv;



use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Uid\Uuid;



/**

 * @brief JSON keys, bounds, and parsing helpers for CV language entries stored under CvProfile content_json.

 *

 * @date 2026-06-10

 * @author Stephane H.

 */

final class LanguagesContract

{

    public const KEY_ENTRIES = 'languageEntries';



    public const MAX_ENTRIES = 20;



    public const MAX_LABEL_LENGTH = 80;



    public const MAX_NOTES_LENGTH = 300;



    /** @var list<string> */

    public const LEVEL_CODES = [

        'native',

        'c2',

        'c1',

        'b2',

        'b1',

        'a2',

        'a1',

    ];



    /** @var array<string, int> Decorative progress bar fill percent keyed by level code. */

    public const LEVEL_PROGRESS_PERCENT = [

        'native' => 100,

        'c2' => 95,

        'c1' => 85,

        'b2' => 70,

        'b1' => 55,

        'a2' => 40,

        'a1' => 25,

    ];



    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';



    /**

     * @brief Parse and normalize language entries from admin POST.

     *

     * @param Request $request HTTP request with nested `language_entries` array.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale used for required label validation.

     * @return list<array<string, mixed>>|null Null when validation fails.

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function parseEntriesFromRequest(Request $request, array $activeLocales, string $defaultLocale): ?array

    {

        $raw = self::parseRawEntriesFromRequest($request);

        if ($raw === null) {

            return null;

        }



        return self::normalizeEntries($raw, $activeLocales, $defaultLocale);

    }



    /**

     * @brief Parse raw language rows from admin POST without final normalization.

     *

     * @param Request $request HTTP request.

     * @return list<array<string, mixed>>|null Null when structure is invalid.

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function parseRawEntriesFromRequest(Request $request): ?array

    {

        $raw = $request->request->all('language_entries');

        if (!is_array($raw)) {

            return null;

        }



        $rows = [];

        foreach ($raw as $row) {

            if (!is_array($row)) {

                return null;

            }



            $rows[] = $row;

        }



        if (count($rows) > self::MAX_ENTRIES) {

            return null;

        }



        return $rows;

    }



    /**

     * @brief Normalize a list of language entries.

     *

     * @param list<array<string, mixed>> $rows Raw rows.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return list<array<string, mixed>>|null Null when any entry is invalid.

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function normalizeEntries(array $rows, array $activeLocales, string $defaultLocale): ?array

    {

        $normalized = [];

        $sortOrder = 0;

        foreach ($rows as $row) {

            if (!is_array($row)) {

                return null;

            }



            $entry = self::normalizeEntry($row, $sortOrder, $activeLocales, $defaultLocale);

            if ($entry === null) {

                return null;

            }



            $normalized[] = $entry;

            ++$sortOrder;

        }



        return $normalized;

    }



    /**

     * @brief Normalize one language entry row with localized labels.

     *

     * @param array<string, mixed> $row Raw row.

     * @param int $sortOrder Display order index.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return array<string, mixed>|null Null when invalid.

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function normalizeEntry(

        array $row,

        int $sortOrder,

        array $activeLocales,

        string $defaultLocale,

    ): ?array {

        $row = self::upgradeLegacyLanguageCodeRow($row, $activeLocales);

        $labelByLocale = self::normalizeLabelByLocale($row, $activeLocales);

        $defaultLabel = $labelByLocale[$defaultLocale] ?? '';

        if ($defaultLabel === '') {

            return null;

        }



        $levelCode = is_string($row['levelCode'] ?? null) ? strtolower(trim($row['levelCode'])) : '';

        if (!in_array($levelCode, self::LEVEL_CODES, true)) {

            return null;

        }



        $id = is_string($row['id'] ?? null) ? trim($row['id']) : '';

        if ($id === '' || !self::isValidUuid($id)) {

            $id = (string) Uuid::v4();

        }



        $notes = is_string($row['notes'] ?? null) ? strip_tags(trim($row['notes'])) : '';

        if (strlen($notes) > self::MAX_NOTES_LENGTH) {

            $notes = substr($notes, 0, self::MAX_NOTES_LENGTH);

        }



        return [

            'id' => $id,

            'labelByLocale' => $labelByLocale,

            'levelCode' => $levelCode,

            'levelProgressPercent' => self::LEVEL_PROGRESS_PERCENT[$levelCode],

            'notes' => $notes,

            'sortOrder' => max(0, $sortOrder),

        ];

    }



    /**

     * @brief Read language entries from decoded CvProfile payload.

     *

     * @param array<string, mixed> $payload Decoded profile JSON.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return list<array<string, mixed>>

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function entriesFromStoredPayload(

        array $payload,

        array $activeLocales = ['fr'],

        string $defaultLocale = 'fr',

    ): array {

        $raw = $payload[self::KEY_ENTRIES] ?? null;

        if (!is_array($raw)) {

            return [];

        }



        $normalized = self::sanitizePersistedEntries(array_values($raw), $activeLocales, $defaultLocale);



        return $normalized;

    }



    /**

     * @brief Whether persisted language entries exist in payload.

     *

     * @param array<string, mixed> $payload Decoded profile JSON.

     * @return bool

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function hasPersistedEntries(array $payload): bool

    {

        return array_key_exists(self::KEY_ENTRIES, $payload)

            && is_array($payload[self::KEY_ENTRIES]);

    }



    /**

     * @brief Re-normalize persisted language rows while keeping valid entries only.

     *

     * @param list<array<string, mixed>> $rows Stored rows.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return list<array<string, mixed>>

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function sanitizePersistedEntries(

        array $rows,

        array $activeLocales = [],

        string $defaultLocale = 'fr',

    ): array {

        if ($activeLocales === []) {

            $activeLocales = self::collectLocalesFromRows($rows);

        }

        if ($activeLocales === []) {

            $activeLocales = ['fr'];

        }

        if (!in_array($defaultLocale, $activeLocales, true)) {

            $defaultLocale = $activeLocales[0];

        }



        $sanitized = [];

        $sortOrder = 0;

        foreach ($rows as $row) {

            if (!is_array($row)) {

                continue;

            }



            $entry = self::normalizeEntry($row, $sortOrder, $activeLocales, $defaultLocale);

            if ($entry === null) {

                continue;

            }



            $sanitized[] = $entry;

            ++$sortOrder;

        }



        return $sanitized;

    }



    /**

     * @brief Validate UUID v4 format.

     *

     * @param string $value Candidate UUID.

     * @return bool

     * @date 2026-06-10

     * @author Stephane H.

     */

    public static function isValidUuid(string $value): bool

    {

        return preg_match(self::UUID_V4_PATTERN, $value) === 1;

    }



    /**

     * @brief Normalize localized labels from POST row.

     *

     * @param array<string, mixed> $row Raw row.

     * @param list<string> $activeLocales Site active locale codes.

     * @return array<string, string>

     * @date 2026-06-10

     * @author Stephane H.

     */

    private static function normalizeLabelByLocale(array $row, array $activeLocales): array

    {

        $rawMap = $row['labelByLocale'] ?? null;

        $labelByLocale = [];

        if (is_array($rawMap)) {

            foreach ($activeLocales as $locale) {

                $label = is_string($rawMap[$locale] ?? null) ? strip_tags(trim($rawMap[$locale])) : '';

                if ($label !== '' && strlen($label) <= self::MAX_LABEL_LENGTH) {

                    $labelByLocale[$locale] = $label;

                }

            }



            return $labelByLocale;

        }



        return $labelByLocale;

    }



    /**

     * @brief Upgrade legacy `languageCode` rows to `labelByLocale` using the code as fallback label.

     *

     * @param array<string, mixed> $row Raw row.

     * @param list<string> $activeLocales Site active locale codes.

     * @return array<string, mixed>

     * @date 2026-06-10

     * @author Stephane H.

     */

    private static function upgradeLegacyLanguageCodeRow(array $row, array $activeLocales): array

    {

        if (is_array($row['labelByLocale'] ?? null) && $row['labelByLocale'] !== []) {

            return $row;

        }



        $languageCode = is_string($row['languageCode'] ?? null) ? strtolower(trim($row['languageCode'])) : '';

        if ($languageCode === '') {

            return $row;

        }



        $fallbackLocales = $activeLocales !== [] ? $activeLocales : ['fr'];

        $labelByLocale = [];

        foreach ($fallbackLocales as $locale) {

            $labelByLocale[$locale] = $languageCode;

        }



        $row['labelByLocale'] = $labelByLocale;

        unset($row['languageCode']);



        return $row;

    }



    /**

     * @brief Collect locale codes present in stored language rows.

     *

     * @param list<array<string, mixed>> $rows Stored rows.

     * @return list<string>

     * @date 2026-06-10

     * @author Stephane H.

     */

    private static function collectLocalesFromRows(array $rows): array

    {

        $locales = [];

        foreach ($rows as $row) {

            if (!is_array($row)) {

                continue;

            }



            $labelByLocale = $row['labelByLocale'] ?? null;

            if (!is_array($labelByLocale)) {

                continue;

            }



            foreach (array_keys($labelByLocale) as $locale) {

                if (is_string($locale) && $locale !== '' && !in_array($locale, $locales, true)) {

                    $locales[] = $locale;

                }

            }

        }



        return $locales;

    }

}

