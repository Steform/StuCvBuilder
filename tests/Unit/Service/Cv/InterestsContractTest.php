<?php



declare(strict_types=1);



namespace App\Tests\Unit\Service\Cv;



use App\Service\Cv\InterestsContract;

use PHPUnit\Framework\TestCase;

use Symfony\Component\HttpFoundation\Request;



/**

 * @brief Unit tests for {@see InterestsContract}.

 *

 * @date 2026-06-09

 * @author Stephane H.

 */

final class InterestsContractTest extends TestCase

{

    /**

     * @brief Valid interest row must normalize with localized labels.

     *

     * @return void

     * @date 2026-06-09

     * @author Stephane H.

     */

    public function testParseEntriesFromRequestAcceptsValidPayload(): void

    {

        $request = new Request([], [

            'interest_entries' => [

                [

                    'id' => '550e8400-e29b-41d4-a716-446655440000',

                    'labelByLocale' => [

                        'fr' => 'Randonnée',

                        'en' => 'Hiking',

                    ],

                    'icon' => 'bi-tree',

                ],

            ],

        ]);



        $parsed = InterestsContract::parseEntriesFromRequest($request, ['fr', 'en'], 'fr');

        self::assertIsArray($parsed);

        self::assertSame('Randonnée', $parsed[0]['labelByLocale']['fr']);

        self::assertSame('Hiking', $parsed[0]['labelByLocale']['en']);

        self::assertSame('bi-tree', $parsed[0]['icon']);
        self::assertSame(InterestsContract::ICON_TYPE_BOOTSTRAP, $parsed[0]['iconType']);

    }

    /**
     * @brief Custom image entries must persist a normalized icon path.
     *
     * @return void
     * @date 2026-06-10
     * @author Stephane H.
     */
    public function testNormalizeEntryAcceptsCustomImageIcon(): void
    {
        $iconPath = 'images/cv/interests/custom/interest-550e8400-e29b-41d4-a716-446655440000-a1b2c3d4.webp';
        $entry = InterestsContract::normalizeEntry(
            [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'iconType' => InterestsContract::ICON_TYPE_IMAGE,
                'iconPath' => $iconPath,
                'labelByLocale' => ['fr' => 'Photo'],
            ],
            0,
            ['fr'],
            'fr',
        );

        self::assertIsArray($entry);
        self::assertSame(InterestsContract::ICON_TYPE_IMAGE, $entry['iconType']);
        self::assertSame($iconPath, $entry['iconPath']);
        self::assertSame('', $entry['icon']);
    }

    /**
     * @brief Image mode without a valid path must be rejected.
     *
     * @return void
     * @date 2026-06-10
     * @author Stephane H.
     */
    /**
     * @brief Columns-per-row setting must clamp to supported Bootstrap grid values.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testNormalizeColumnsPerRowClampsToSupportedRange(): void
    {
        self::assertSame(4, InterestsContract::normalizeColumnsPerRow(null));
        self::assertSame(2, InterestsContract::normalizeColumnsPerRow('2'));
        self::assertSame(6, InterestsContract::normalizeColumnsPerRow(9));
        self::assertSame(3, InterestsContract::columnsPerRowFromPayload([
            InterestsContract::KEY_COLUMNS_PER_ROW => 3,
        ]));
    }

    public function testNormalizeEntryRejectsImageWithoutPath(): void
    {
        $entry = InterestsContract::normalizeEntry(
            [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'iconType' => InterestsContract::ICON_TYPE_IMAGE,
                'labelByLocale' => ['fr' => 'Photo'],
            ],
            0,
            ['fr'],
            'fr',
        );

        self::assertNull($entry);
    }



    /**

     * @brief Legacy per-locale storage must migrate into canonical entries.

     *

     * @return void

     * @date 2026-06-09

     * @author Stephane H.

     */

    public function testMigrateLegacyEntriesByLocaleMergesLabels(): void

    {

        $legacy = [

            'fr' => [

                ['id' => '550e8400-e29b-41d4-a716-446655440000', 'label' => 'Randonnée', 'icon' => 'bi-tree'],

            ],

            'en' => [

                ['id' => '550e8400-e29b-41d4-a716-446655440000', 'label' => 'Hiking', 'icon' => 'bi-tree'],

            ],

        ];



        $migrated = InterestsContract::migrateLegacyEntriesByLocale($legacy, ['fr', 'en'], 'fr');

        self::assertCount(1, $migrated);

        self::assertSame('Randonnée', $migrated[0]['labelByLocale']['fr']);

        self::assertSame('Hiking', $migrated[0]['labelByLocale']['en']);

    }



    /**

     * @brief Default locale label is required when saving from admin.

     *

     * @return void

     * @date 2026-06-09

     * @author Stephane H.

     */

    public function testParseEntriesFromRequestRejectsMissingDefaultLocaleLabel(): void

    {

        $request = new Request([], [

            'interest_entries' => [

                [

                    'labelByLocale' => [

                        'en' => 'Hiking',

                    ],

                ],

            ],

        ]);



        self::assertNull(InterestsContract::parseEntriesFromRequest($request, ['fr', 'en'], 'fr'));

    }

}

