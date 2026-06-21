<?php



declare(strict_types=1);



namespace App\Tests\Unit\Cv;



use App\Cv\CompanyCvLanguagesOverrideScope;

use App\Service\Cv\LanguagesContract;

use PHPUnit\Framework\TestCase;



/**

 * @brief Unit tests for {@see CompanyCvLanguagesOverrideScope}.

 *

 * @date 2026-06-10

 * @author Stephane H.

 */

final class CompanyCvLanguagesOverrideScopeTest extends TestCase

{

    /**

     * @brief Merge applies language entries onto base payload.

     *

     * @return void

     * @date 2026-06-10

     * @author Stephane H.

     */

    public function testMergeIntoPayloadReplacesLanguageEntries(): void

    {

        $base = [

            'title' => 'CV',

            LanguagesContract::KEY_ENTRIES => [[

                'id' => '11111111-1111-4111-8111-111111111111',

                'sortOrder' => 0,

                'labelByLocale' => ['fr' => 'Anglais'],

                'levelCode' => 'b2',

                'levelProgressPercent' => 70,

                'notes' => '',

            ]],

        ];



        $override = [

            LanguagesContract::KEY_ENTRIES => [[

                'id' => '22222222-2222-4222-8222-222222222222',

                'sortOrder' => 0,

                'labelByLocale' => ['fr' => 'Français'],

                'levelCode' => 'native',

                'levelProgressPercent' => 100,

                'notes' => 'Langue maternelle',

            ]],

        ];



        $merged = CompanyCvLanguagesOverrideScope::mergeIntoPayload($base, $override);



        self::assertSame('Français', $merged[LanguagesContract::KEY_ENTRIES][0]['labelByLocale']['fr']);

        self::assertSame('CV', $merged['title']);

    }

}

