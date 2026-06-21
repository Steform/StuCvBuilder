<?php



declare(strict_types=1);



namespace App\Tests\Unit\Service\Cv;



use App\Service\Cv\CertificationContract;

use PHPUnit\Framework\TestCase;

use Symfony\Component\HttpFoundation\Request;



/**

 * @brief Unit tests for {@see CertificationContract} parsing and validation.

 * @date 2026-06-11

 * @author Stephane H.

 */

final class CertificationContractTest extends TestCase

{

    /**

     * @brief Valid flat request payload must normalize to canonical entries.

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testParseEntriesFromRequestAcceptsValidPayload(): void

    {

        $request = new Request([], [

            'certification_entries' => [

                [

                    'id' => '550e8400-e29b-41d4-a716-446655440000',

                    'sortOrder' => '0',

                    'startDate' => '2018-01',

                    'endDate' => '2022-12',

                    'isCurrent' => '0',

                    'titleByLocale' => [

                        'fr' => 'Technicien',

                        'en' => 'Technician',

                    ],

                    'providerNameByLocale' => [

                        'fr' => 'CKELPROCESS',

                        'en' => 'CKELPROCESS',

                    ],

                    'locationByLocale' => [

                        'fr' => 'Paris, France',

                    ],

                    'highlightsByLocale' => [

                        'fr' => ['Line one', ''],

                    ],

                    'isPrimary' => '1',

                ],

            ],

        ]);



        $parsed = CertificationContract::parseEntriesFromRequest($request, ['fr', 'en'], 'fr');

        self::assertIsArray($parsed);

        self::assertCount(1, $parsed);

        self::assertSame('Technicien', $parsed[0]['titleByLocale']['fr']);

        self::assertSame('Paris, France', $parsed[0]['locationByLocale']['fr']);

        self::assertSame(['Line one'], $parsed[0]['highlightsByLocale']['fr']);

    }



    /**

     * @brief Unchecked primary checkbox must persist as false via hidden-field POST values.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testParseEntriesFromRequestPersistsUncheckedPrimaryFlag(): void

    {

        $request = new Request([], [

            'certification_entries' => [

                [

                    'id' => '550e8400-e29b-41d4-a716-446655440000',

                    'sortOrder' => '0',

                    'startDate' => '2018-01',

                    'endDate' => '2022-12',

                    'isCurrent' => ['0'],

                    'titleByLocale' => ['fr' => 'Technicien'],

                    'providerNameByLocale' => ['fr' => 'CKELPROCESS'],

                    'highlightsByLocale' => ['fr' => ['Line one']],

                    'isPrimary' => ['0'],

                ],

            ],

        ]);



        $parsed = CertificationContract::parseEntriesFromRequest($request, ['fr'], 'fr');

        self::assertIsArray($parsed);

        self::assertFalse($parsed[0]['isPrimary']);

    }



    /**

     * @brief Legacy isVisible false must map to secondary placement when isPrimary is absent.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testNormalizeLegacyProjectedEntryMapsLegacyHiddenVisibleToSecondary(): void

    {

        $entry = CertificationContract::normalizeLegacyProjectedEntry([

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'startDate' => '2018-01',

            'endDate' => '2022-12',

            'title' => 'Legacy role',

            'providerName' => 'Legacy Co',

            'highlights' => ['Detail'],

            'isVisible' => false,

        ], 0);



        self::assertIsArray($entry);

        self::assertFalse($entry['isPrimary']);

        self::assertArrayNotHasKey('isVisible', $entry);

    }



    /**

     * @brief Invalid end date before start date must be rejected.

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testParseEntriesFromRequestRejectsInvalidDateRange(): void

    {

        $request = new Request([], [

            'certification_entries' => [

                [

                    'id' => '550e8400-e29b-41d4-a716-446655440000',

                    'startDate' => '2020-01',

                    'endDate' => '2019-01',

                    'titleByLocale' => ['fr' => 'Role'],

                    'providerNameByLocale' => ['fr' => 'Co'],

                ],

            ],

        ]);



        self::assertNull(CertificationContract::parseEntriesFromRequest($request, ['fr'], 'fr'));

    }



    /**

     * @brief Current role may omit end date.

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testNormalizeEntryAllowsCurrentRoleWithoutEndDate(): void

    {

        $entry = CertificationContract::normalizeEntry([

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'startDate' => '2024-06',

            'isCurrent' => true,

            'titleByLocale' => ['fr' => 'Developer'],

            'providerNameByLocale' => ['fr' => 'Acme'],

            'highlightsByLocale' => [],

        ], 0, ['fr'], 'fr');



        self::assertIsArray($entry);

        self::assertTrue($entry['isCurrent']);

        self::assertNull($entry['endDate']);

    }



    /**

     * @brief Missing end date defaults to start date for completed certifications.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testNormalizeEntryDefaultsEndDateToStartDateWhenMissing(): void

    {

        $entry = CertificationContract::normalizeEntry([

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'startDate' => '2022-04',

            'titleByLocale' => ['fr' => 'Docker'],

            'providerNameByLocale' => ['fr' => 'Udemy'],

            'highlightsByLocale' => [],

        ], 0, ['fr'], 'fr');



        self::assertIsArray($entry);

        self::assertSame('2022-04', $entry['endDate']);

    }



    /**

     * @brief Empty default-locale provider name must be rejected.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testNormalizeEntryRejectsEmptyProviderName(): void

    {

        self::assertNull(CertificationContract::normalizeEntry([

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'startDate' => '2020-01',

            'endDate' => '2021-12',

            'titleByLocale' => ['fr' => 'Role'],

            'providerNameByLocale' => ['fr' => ''],

            'highlightsByLocale' => [],

        ], 0, ['fr'], 'fr'));

    }

    /**

     * @brief Valid stored proof PDF paths must normalize to the custom upload prefix.

     *

     * @return void

     * @date 2026-05-31

     * @author Stephane H.

     */

    public function testNormalizeStoredProofPdfPathAcceptsCustomUpload(): void

    {

        $path = CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-abc12345-deadbeef.pdf';



        self::assertSame($path, CertificationContract::normalizeStoredProofPdfPath($path));

    }



    /**

     * @brief Path traversal and non-PDF extensions must be rejected.

     *

     * @return void

     * @date 2026-05-31

     * @author Stephane H.

     */

    public function testNormalizeStoredProofPdfPathRejectsUnsafeValues(): void

    {

        self::assertNull(CertificationContract::normalizeStoredProofPdfPath('../documents/evil.pdf'));

        self::assertNull(CertificationContract::normalizeStoredProofPdfPath('images/home/custom/logo.webp'));

        self::assertNull(CertificationContract::normalizeStoredProofPdfPath(''));

        self::assertNull(CertificationContract::normalizeStoredProofPdfPath(null));

    }



    /**

     * @brief normalizeEntry must persist proofPdfPath when valid.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testNormalizeEntryPersistsProofPdfPath(): void

    {

        $proofPath = CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-test.pdf';

        $entry = CertificationContract::normalizeEntry([

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'startDate' => '2022-04',

            'titleByLocale' => ['fr' => 'Docker'],

            'providerNameByLocale' => ['fr' => 'Udemy'],

            'highlightsByLocale' => [],

            'proofPdfPath' => $proofPath,

        ], 0, ['fr'], 'fr');



        self::assertIsArray($entry);

        self::assertSame($proofPath, $entry['proofPdfPath']);

    }



    /**

     * @brief normalizeEntry must persist proofUrl when valid.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testNormalizeEntryPersistsProofUrl(): void

    {

        $proofUrl = 'https://openbadgefactory.com/public/assertions/example';

        $entry = CertificationContract::normalizeEntry([

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'startDate' => '2022-04',

            'titleByLocale' => ['fr' => 'Python'],

            'providerNameByLocale' => ['fr' => 'FUN MOOC'],

            'highlightsByLocale' => [],

            'proofUrl' => $proofUrl,

        ], 0, ['fr'], 'fr');



        self::assertIsArray($entry);

        self::assertSame($proofUrl, $entry['proofUrl']);

    }



    /**

     * @brief Invalid proof URLs must be rejected during normalization.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testNormalizeEntryRejectsInvalidProofUrl(): void

    {

        $entry = CertificationContract::normalizeEntry([

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'startDate' => '2022-04',

            'titleByLocale' => ['fr' => 'Python'],

            'providerNameByLocale' => ['fr' => 'FUN MOOC'],

            'highlightsByLocale' => [],

            'proofUrl' => 'javascript:alert(1)',

        ], 0, ['fr'], 'fr');



        self::assertIsArray($entry);

        self::assertNull($entry['proofUrl']);

    }



    /**

     * @brief Legacy per-locale rows must migrate into one canonical entry per sortOrder slot.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testMigrateLegacyEntriesByLocaleMergesLocalizedMaps(): void

    {

        $proofPath = CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-shared.pdf';

        $migrated = CertificationContract::migrateLegacyEntriesByLocale(

            [

                'fr' => [[

                    'id' => '11111111-1111-4111-8111-111111111111',

                    'sortOrder' => 0,

                    'startDate' => '2020-01',

                    'endDate' => '2020-12',

                    'isCurrent' => false,

                    'title' => 'Certificat FR',

                    'providerName' => 'Organisme FR',

                    'location' => 'Paris',

                    'highlights' => ['Point FR'],

                    'isPrimary' => true,

                    'proofPdfPath' => $proofPath,

                ]],

                'en' => [[

                    'id' => '22222222-2222-4222-8222-222222222222',

                    'sortOrder' => 0,

                    'startDate' => '2020-01',

                    'endDate' => '2020-12',

                    'isCurrent' => false,

                    'title' => 'EN certificate',

                    'providerName' => 'EN provider',

                    'proofPdfPath' => $proofPath,

                    'highlights' => ['EN point'],

                    'isPrimary' => true,

                ]],

            ],

            ['fr', 'en'],

            'fr',

        );



        self::assertCount(1, $migrated);

        self::assertSame('Certificat FR', $migrated[0]['titleByLocale']['fr']);

        self::assertSame('EN certificate', $migrated[0]['titleByLocale']['en']);

        self::assertSame($proofPath, $migrated[0]['proofPdfPath']);

        self::assertSame(['Point FR'], $migrated[0]['highlightsByLocale']['fr']);

        self::assertSame(['EN point'], $migrated[0]['highlightsByLocale']['en']);

    }



    /**

     * @brief entriesFromStoredPayload must read canonical key or migrate legacy map.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testEntriesFromStoredPayloadPrefersCanonicalKey(): void

    {

        $canonical = [[

            'id' => '550e8400-e29b-41d4-a716-446655440000',

            'sortOrder' => 0,

            'startDate' => '2020-01',

            'endDate' => '2020-12',

            'isCurrent' => false,

            'titleByLocale' => ['fr' => 'Canonical'],

            'providerNameByLocale' => ['fr' => 'Provider'],

            'locationByLocale' => [],

            'highlightsByLocale' => [],

            'isPrimary' => true,

        ]];



        $fromCanonical = CertificationContract::entriesFromStoredPayload(

            [CertificationContract::KEY_ENTRIES => $canonical],

            ['fr'],

            'fr',

        );

        self::assertSame('Canonical', $fromCanonical[0]['titleByLocale']['fr']);



        $fromLegacy = CertificationContract::entriesFromStoredPayload(

            [

                CertificationContract::KEY_ENTRIES_BY_LOCALE => [

                    'fr' => [[

                        'id' => '550e8400-e29b-41d4-a716-446655440000',

                        'sortOrder' => 0,

                        'startDate' => '2020-01',

                        'endDate' => '2020-12',

                        'isCurrent' => false,

                        'title' => 'Legacy',

                        'providerName' => 'Legacy Co',

                        'highlights' => [],

                        'isPrimary' => true,

                    ]],

                ],

            ],

            ['fr'],

            'fr',

        );

        self::assertSame('Legacy', $fromLegacy[0]['titleByLocale']['fr']);

    }

}


