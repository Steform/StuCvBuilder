<?php



declare(strict_types=1);



namespace App\Tests\Unit\Service\Cv;



use App\Service\Cv\CvCertificationSettingsService;

use App\Service\Cv\CertificationContract;

use App\Tests\Support\CvPdfPlaceholderTestTranslator;

use PHPUnit\Framework\TestCase;



/**

 * @brief Unit tests for {@see CvCertificationSettingsService}.

 * @date 2026-06-11

 * @author Stephane H.

 */

final class CvCertificationSettingsServiceTest extends TestCase

{

    private CvCertificationSettingsService $service;



    protected function setUp(): void

    {

        $this->service = new CvCertificationSettingsService(

            CvPdfPlaceholderTestTranslator::create(),

        );

    }



    /**

     * @brief Empty JSON without persisted entries must yield a single generic placeholder entry.

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testResolveFromContentJsonPlaceholderWhenMapMissing(): void

    {

        $resolved = $this->service->resolveFromContentJson('{}', ['fr'], 'fr', 'fr');



        self::assertFalse($resolved['hasPersistedEntries']);

        self::assertCount(1, $resolved['entries']);

        self::assertStringContainsString('cv.placeholder.certification.title', (string) ($resolved['entries'][0]['title'] ?? ''));

        self::assertFalse($resolved['hasSecondaryVisible']);

    }



    /**

     * @brief Placeholder mode returns the same generic row as an empty persisted profile.

     * @return void

     * @date 2026-05-17

     * @author Stephane H.

     */

    public function testResolveFromContentJsonPlaceholderWhenActive(): void

    {

        $resolved = $this->service->resolveFromContentJson('{}', ['fr'], 'fr', 'fr');



        self::assertCount(1, $resolved['entries']);

        self::assertStringContainsString('cv.placeholder.certification.title', (string) ($resolved['entries'][0]['title'] ?? ''));

    }



    /**

     * @brief Primary filter keeps only visible primary entries.

     * @return void

     * @date 2026-05-15

     * @author Stephane H.

     */

    public function testFilterPrimaryVisible(): void

    {

        $entries = [

            ['sortOrder' => 0, 'isPrimary' => true, 'title' => 'A'],

            ['sortOrder' => 1, 'isPrimary' => false, 'title' => 'B'],

            ['sortOrder' => 2, 'isPrimary' => true, 'title' => 'C'],

        ];



        $filtered = $this->service->filterPrimaryVisible($entries);

        self::assertCount(2, $filtered);

        self::assertSame('A', $filtered[0]['title']);

        self::assertSame('C', $filtered[1]['title']);

    }



    /**

     * @brief Period label uses current translation when isCurrent is true.

     * @return void

     * @date 2026-05-15

     * @author Stephane H.

     */

    public function testBuildPeriodLabelForCurrentRole(): void

    {

        $label = $this->service->buildPeriodLabel([

            'startDate' => '2024-01',

            'isCurrent' => true,

        ], 'fr');



        self::assertStringContainsString('2024', $label);

    }



    /**

     * @brief Persisted canonical entries in JSON disable placeholder seeding for empty locale list.

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testResolveUsesPersistedEntriesWhenPresent(): void

    {

        $payload = [

            CertificationContract::KEY_ENTRIES => [

                [

                    'id' => '550e8400-e29b-41d4-a716-446655440000',

                    'sortOrder' => 0,

                    'startDate' => '2020-01',

                    'endDate' => '2021-12',

                    'isCurrent' => false,

                    'titleByLocale' => ['fr' => 'Custom role'],

                    'providerNameByLocale' => ['fr' => 'Custom Co'],

                    'locationByLocale' => [],

                    'highlightsByLocale' => [],

                    'isPrimary' => true,

                ],

            ],

        ];



        $resolved = $this->service->resolveFromContentJson((string) json_encode($payload), ['fr'], 'fr', 'fr');

        self::assertTrue($resolved['hasPersistedEntries']);

        self::assertSame('Custom role', $resolved['entries'][0]['title']);

    }



    /**

     * @brief Admin preview map must filter primary visible entries per locale and detect secondary rows.

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testBuildAdminPreviewPayloadByLocaleFiltersPerLocale(): void

    {

        $canonicalEntries = [

            [

                'id' => '11111111-1111-4111-8111-111111111111',

                'sortOrder' => 0,

                'startDate' => '2020-01',

                'endDate' => '2020-12',

                'isCurrent' => false,

                'titleByLocale' => ['fr' => 'FR primary', 'en' => 'EN primary'],

                'providerNameByLocale' => ['fr' => 'Org FR', 'en' => 'Org EN'],

                'locationByLocale' => [],

                'highlightsByLocale' => [],

                'isPrimary' => true,

            ],

            [

                'id' => '22222222-2222-4222-8222-222222222222',

                'sortOrder' => 1,

                'startDate' => '2019-01',

                'endDate' => '2019-12',

                'isCurrent' => false,

                'titleByLocale' => ['fr' => 'FR secondary'],

                'providerNameByLocale' => ['fr' => 'Org FR 2'],

                'locationByLocale' => [],

                'highlightsByLocale' => [],

                'isPrimary' => false,

            ],

        ];



        $preview = $this->service->buildAdminPreviewPayloadByLocale($canonicalEntries, ['fr', 'en'], 'fr');



        self::assertCount(1, $preview['fr']['entries']);

        self::assertSame('FR primary', $preview['fr']['entries'][0]['title']);

        self::assertTrue($preview['fr']['hasSecondaryVisible']);

        self::assertCount(1, $preview['en']['entries']);

        self::assertSame('EN primary', $preview['en']['entries'][0]['title']);

        self::assertTrue($preview['en']['hasSecondaryVisible']);

    }

    /**

     * @brief Full certification page payload must mark secondary-only rows for highlight styling.

     *

     * @return void

     * @date 2026-05-31

     * @author Stephane H.

     */

    public function testResolveAllMarksHiddenOnPrimaryForSecondaryEntries(): void

    {

        $entries = [

            ['sortOrder' => 0, 'isPrimary' => true, 'title' => 'Primary'],

            ['sortOrder' => 1, 'isPrimary' => false, 'title' => 'Secondary'],

        ];



        $full = $this->service->resolveAll($entries);



        self::assertCount(2, $full);

        self::assertFalse($full[0]['hiddenOnPrimary']);

        self::assertTrue($full[1]['hiddenOnPrimary']);

    }



    /**

     * @brief Proof PDF is per canonical entry without English locale fallback.

     *

     * @return void

     * @date 2026-06-11

     * @author Stephane H.

     */

    public function testResolveFromContentJsonUsesEntryProofWithoutEnglishFallback(): void

    {

        $englishPath = CertificationContract::CERTIFICATION_PROOF_PDF_PATH_PREFIX.'certification-proof-en.pdf';

        $payload = [

            CertificationContract::KEY_ENTRIES => [

                [

                    'id' => '11111111-1111-4111-8111-111111111111',

                    'sortOrder' => 0,

                    'startDate' => '2020-01',

                    'endDate' => '2020-12',

                    'isCurrent' => false,

                    'titleByLocale' => ['fr' => 'Certificat FR', 'en' => 'EN certificate'],

                    'providerNameByLocale' => ['fr' => 'Organisme FR', 'en' => 'EN provider'],

                    'locationByLocale' => [],

                    'highlightsByLocale' => [],

                    'proofPdfPath' => $englishPath,

                    'isPrimary' => true,

                ],

            ],

        ];



        $resolved = $this->service->resolveFromContentJson(

            (string) json_encode($payload),

            ['fr', 'en'],

            'en',

            'fr'

        );



        self::assertSame('Certificat FR', $resolved['entries'][0]['title']);

        self::assertSame($englishPath, $resolved['entries'][0]['proofPdfPath'] ?? null);

    }

}


