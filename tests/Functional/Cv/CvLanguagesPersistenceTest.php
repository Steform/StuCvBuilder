<?php



declare(strict_types=1);



namespace App\Tests\Functional\Cv;



use App\Cv\CvProfilePersistenceScope;

use App\Service\Cv\CvLanguagesSettingsService;

use App\Service\Cv\LanguagesContract;

use App\Tests\Support\CvPdfPlaceholderTestTranslator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;



/**

 * @brief Functional checks for languages JSON persistence contract.

 *

 * @date 2026-06-10

 * @author Stephane H.

 */

final class CvLanguagesPersistenceTest extends KernelTestCase

{

    /**

     * @brief Service must be registered in the container.

     *

     * @return void

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function testCvLanguagesSettingsServiceIsRegistered(): void

    {

        self::bootKernel();

        self::assertInstanceOf(

            CvLanguagesSettingsService::class,

            static::getContainer()->get(CvLanguagesSettingsService::class)

        );

    }



    /**

     * @brief Serialized payload round-trip keeps language entries after sanitization.

     *

     * @return void

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function testLanguagesPayloadRoundTrip(): void

    {

        $service = new CvLanguagesSettingsService(CvPdfPlaceholderTestTranslator::create());

        $payload = [

            LanguagesContract::KEY_ENTRIES => [

                [

                    'id' => '550e8400-e29b-41d4-a716-446655440000',

                    'sortOrder' => 0,

                    'labelByLocale' => [

                        'fr' => 'Français',

                        'lt' => 'Prancūzų',

                    ],

                    'levelCode' => 'native',

                    'notes' => 'Mother tongue',

                ],

            ],

        ];



        $sanitized = CvProfilePersistenceScope::sanitizeForPersistence($payload);

        $json = (string) json_encode($sanitized, JSON_UNESCAPED_UNICODE);

        $resolved = $service->resolveFromContentJson($json, ['fr', 'lt'], 'fr', 'lt');



        self::assertTrue($resolved['hasPersistedEntries']);

        self::assertCount(1, $resolved['entries']);

        self::assertSame('Prancūzų', $resolved['entries'][0]['languageLabel']);

        self::assertSame('native', $resolved['entries'][0]['levelCode']);

    }

}

