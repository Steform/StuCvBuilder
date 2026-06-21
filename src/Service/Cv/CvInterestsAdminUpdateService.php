<?php



declare(strict_types=1);



namespace App\Service\Cv;



use Symfony\Component\HttpFoundation\File\UploadedFile;

use Symfony\Component\HttpFoundation\Request;



/**

 * @brief Apply admin Interests customization POST fields to a CV content JSON payload slice.

 *

 * @date 2026-06-09

 * @author Stephane H.

 */

class CvInterestsAdminUpdateService

{

    public function __construct(

        private readonly CvInterestsIconUploadService $cvInterestsIconUploadService,

    ) {

    }



    /**

     * @brief Parse and normalize interest entries from an admin POST request.

     *

     * @param array<string, mixed> $payload Existing payload slice or full profile.

     * @param Request $request HTTP request with nested `interest_entries`.

     * @param list<string> $activeLocales Site active locale codes.

     * @return array{

     *     payload: array<string, mixed>,

     *     flashSuccess: list<string>,

     *     flashWarning: list<string>,

     *     flashError: list<string>

     * }

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function applyInterestsFromRequest(

        array $payload,

        Request $request,

        array $activeLocales,

        string $defaultLocale,

    ): array {

        $flashSuccess = [];

        $flashWarning = [];

        $flashError = [];



        $raw = InterestsContract::parseRawEntriesFromRequest($request);

        if ($raw === null) {

            $flashError[] = 'dashboard.customization_cv.flash.interests_invalid';



            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');

        }



        $existingEntries = InterestsContract::entriesFromStoredPayload($payload, $activeLocales, $defaultLocale);

        $existingById = $this->indexEntriesById($existingEntries);

        $uploadedRows = $request->files->all('interest_entries');



        $processedRows = [];

        foreach ($raw as $index => $row) {

            if (!is_array($row)) {

                $flashError[] = 'dashboard.customization_cv.flash.interests_invalid';



                return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');

            }



            $uploadedFile = null;

            if (isset($uploadedRows[$index]) && is_array($uploadedRows[$index])) {

                $candidate = $uploadedRows[$index]['iconFile'] ?? null;

                if ($candidate instanceof UploadedFile) {

                    $uploadedFile = $candidate;

                }

            }



            try {

                $processedRows[] = $this->mergeIconFieldsFromRequest($row, $uploadedFile, $existingById);

            } catch (\InvalidArgumentException $exception) {

                $messageKey = $exception->getMessage();

                $flashError[] = $messageKey !== '' ? $messageKey : 'dashboard.customization_cv.interests.flash_invalid_icon';



                return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');

            }

        }



        $parsed = InterestsContract::normalizeEntries($processedRows, $activeLocales, $defaultLocale);

        if ($parsed === null) {

            $flashError[] = 'dashboard.customization_cv.flash.interests_invalid';



            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');

        }



        $this->purgeRemovedCustomIcons($existingEntries, $parsed);



        $payload[InterestsContract::KEY_ENTRIES] = $parsed;

        unset($payload[InterestsContract::LEGACY_KEY_ENTRIES_BY_LOCALE]);

        $payload[InterestsContract::KEY_COLUMNS_PER_ROW] = InterestsContract::normalizeColumnsPerRow(
            $request->request->get('interests_columns_per_row', $payload[InterestsContract::KEY_COLUMNS_PER_ROW] ?? null)
        );

        return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');

    }



    /**

     * @brief Index stored entries by UUID for icon replacement lookups.

     *

     * @param list<array<string, mixed>> $entries Stored entries.

     * @return array<string, array<string, mixed>>

     * @date 2026-06-10

     * @author Stephane H.

     */

    private function indexEntriesById(array $entries): array

    {

        $indexed = [];

        foreach ($entries as $entry) {

            if (!is_array($entry)) {

                continue;

            }



            $id = is_string($entry['id'] ?? null) ? trim($entry['id']) : '';

            if ($id !== '' && InterestsContract::isValidUuid($id)) {

                $indexed[$id] = $entry;

            }

        }



        return $indexed;

    }



    /**

     * @brief Merge icon type, bootstrap class, and custom path from POST row and optional upload.

     *

     * @param array<string, mixed> $row Raw POST row.

     * @param UploadedFile|null $uploadedFile Optional icon upload.

     * @param array<string, array<string, mixed>> $existingById Previously stored entries keyed by id.

     * @return array<string, mixed>

     * @date 2026-06-10

     * @author Stephane H.

     */

    private function mergeIconFieldsFromRequest(

        array $row,

        ?UploadedFile $uploadedFile,

        array $existingById,

    ): array {

        $entryId = is_string($row['id'] ?? null) ? trim($row['id']) : '';

        $existing = $entryId !== '' ? ($existingById[$entryId] ?? null) : null;

        $iconType = is_string($row['iconType'] ?? null) ? trim($row['iconType']) : InterestsContract::ICON_TYPE_BOOTSTRAP;



        if ($uploadedFile instanceof UploadedFile) {

            $this->cvInterestsIconUploadService->deleteIfNeeded(

                is_array($existing) ? ($existing['iconPath'] ?? null) : null

            );

            $row['iconType'] = InterestsContract::ICON_TYPE_IMAGE;

            $row['icon'] = '';

            $row['iconPath'] = $this->cvInterestsIconUploadService->store(

                $uploadedFile,

                $entryId !== '' ? $entryId : 'interest'

            );



            return $row;

        }



        if ($iconType === InterestsContract::ICON_TYPE_IMAGE) {

            $iconPath = InterestsContract::normalizeIconPath($row['iconPath'] ?? null);

            if ($iconPath === null && is_array($existing)) {

                $iconPath = InterestsContract::normalizeIconPath($existing['iconPath'] ?? null);

            }



            if ($iconPath === null) {

                throw new \InvalidArgumentException('dashboard.customization_cv.interests.flash_invalid_icon');

            }



            $row['iconType'] = InterestsContract::ICON_TYPE_IMAGE;

            $row['icon'] = '';

            $row['iconPath'] = $iconPath;



            return $row;

        }



        if (is_array($existing) && ($existing['iconType'] ?? '') === InterestsContract::ICON_TYPE_IMAGE) {

            $this->cvInterestsIconUploadService->deleteIfNeeded($existing['iconPath'] ?? null);

        }



        $row['iconType'] = InterestsContract::ICON_TYPE_BOOTSTRAP;

        $row['iconPath'] = '';



        return $row;

    }



    /**

     * @brief Delete custom icon files removed or replaced during save.

     *

     * @param list<array<string, mixed>> $previousEntries Entries before save.

     * @param list<array<string, mixed>> $nextEntries Entries after save.

     * @return void

     * @date 2026-06-10

     * @author Stephane H.

     */

    private function purgeRemovedCustomIcons(array $previousEntries, array $nextEntries): void

    {

        $previousPaths = InterestsContract::collectCustomIconPaths($previousEntries);

        $nextPaths = array_fill_keys(InterestsContract::collectCustomIconPaths($nextEntries), true);



        foreach ($previousPaths as $path) {

            if (!isset($nextPaths[$path])) {

                $this->cvInterestsIconUploadService->deleteIfNeeded($path);

            }

        }

    }

}

