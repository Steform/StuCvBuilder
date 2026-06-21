<?php



declare(strict_types=1);



namespace App\Tests\Unit\Service\Cv;



use App\Service\Cv\LanguagesContract;

use PHPUnit\Framework\TestCase;

use Symfony\Component\HttpFoundation\Request;



/**

 * @brief Unit tests for {@see LanguagesContract} parsing and validation.

 *

 * @date 2026-06-10

 * @author Stephane H.

 */

final class LanguagesContractTest extends TestCase

{

    /**

     * @brief Valid request payload must normalize to structured language entries.

     *

     * @return void

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function testParseEntriesFromRequestAcceptsValidPayload(): void

    {

        $request = new Request([], [

            'language_entries' => [

                [

                    'id' => '550e8400-e29b-41d4-a716-446655440000',

                    'sortOrder' => '0',

                    'labelByLocale' => [

                        'fr' => 'Français',

                        'en' => 'French',

                    ],

                    'levelCode' => 'native',

                    'notes' => 'Mother tongue',

                ],

            ],

        ]);



        $parsed = LanguagesContract::parseEntriesFromRequest($request, ['fr', 'en'], 'fr');

        self::assertIsArray($parsed);

        self::assertCount(1, $parsed);

        self::assertSame('Français', $parsed[0]['labelByLocale']['fr']);

        self::assertSame('native', $parsed[0]['levelCode']);

    }



    /**

     * @brief Missing default locale label must reject the payload.

     *

     * @return void

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function testParseEntriesFromRequestRejectsMissingDefaultLocaleLabel(): void

    {

        $request = new Request([], [

            'language_entries' => [

                [

                    'labelByLocale' => [

                        'en' => 'French',

                    ],

                    'levelCode' => 'b1',

                ],

            ],

        ]);



        self::assertNull(LanguagesContract::parseEntriesFromRequest($request, ['fr', 'en'], 'fr'));

    }



    /**

     * @brief Legacy language code rows must upgrade to localized labels during sanitization.

     *

     * @return void

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function testSanitizePersistedEntriesUpgradesLegacyLanguageCode(): void

    {

        $sanitized = LanguagesContract::sanitizePersistedEntries([

            [

                'id' => '550e8400-e29b-41d4-a716-446655440000',

                'languageCode' => 'no',

                'levelCode' => 'a1',

            ],

        ], ['lt'], 'lt');



        self::assertCount(1, $sanitized);

        self::assertSame('no', $sanitized[0]['labelByLocale']['lt']);

        self::assertArrayNotHasKey('languageCode', $sanitized[0]);

    }

}

