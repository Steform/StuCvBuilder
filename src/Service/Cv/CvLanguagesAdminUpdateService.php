<?php



declare(strict_types=1);



namespace App\Service\Cv;



use Symfony\Component\HttpFoundation\Request;



/**

 * @brief Apply admin Languages customization POST fields to a CV content JSON payload slice.

 *

 * @date 2026-06-10

 * @author Stephane H.

 */

class CvLanguagesAdminUpdateService

{

    /**

     * @brief Parse and normalize language entries from an admin POST request.

     *

     * @param array<string, mixed> $payload Existing payload slice or full profile.

     * @param Request $request HTTP request with nested `language_entries`.

     * @param list<string> $activeLocales Site active locale codes.

     * @param string $defaultLocale Site default locale.

     * @return array{

     *     payload: array<string, mixed>,

     *     flashSuccess: list<string>,

     *     flashWarning: list<string>,

     *     flashError: list<string>

     * }

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function applyLanguagesFromRequest(

        array $payload,

        Request $request,

        array $activeLocales,

        string $defaultLocale,

    ): array {

        $flashSuccess = [];

        $flashWarning = [];

        $flashError = [];



        $parsed = LanguagesContract::parseEntriesFromRequest($request, $activeLocales, $defaultLocale);

        if ($parsed === null) {

            $flashError[] = 'dashboard.customization_cv.flash.languages_invalid';



            return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');

        }



        $payload[LanguagesContract::KEY_ENTRIES] = $parsed;



        return compact('payload', 'flashSuccess', 'flashWarning', 'flashError');

    }

}

