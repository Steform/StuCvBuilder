<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvSituationOverrideScope;
use App\Service\Cv\SituationContentContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvSituationOverrideScopeTest extends TestCase
{
    /**
     * @brief Merge applies situation content map onto base payload.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testMergeIntoPayloadReplacesSituationContentByLocale(): void
    {
        $base = [
            'title' => 'CV',
            SituationContentContract::KEY_CONTENT_BY_LOCALE => [
                'fr' => [
                    'statusLabel' => 'Global',
                    'introLead' => 'Global intro',
                    'contractChip' => 'CDI',
                    'searchWhereChips' => [['label' => 'France', 'variant' => 'secondary']],
                    'searchModeChips' => [['label' => 'Remote', 'variant' => 'secondary']],
                    'searchFocusChips' => [['label' => 'PHP', 'variant' => 'primary']],
                ],
            ],
        ];

        $override = [
            SituationContentContract::KEY_CONTENT_BY_LOCALE => [
                'fr' => [
                    'statusLabel' => 'Company',
                    'introLead' => 'Company intro',
                    'contractChip' => 'Freelance',
                    'searchWhereChips' => [['label' => 'Norway', 'variant' => 'primary']],
                    'searchModeChips' => [['label' => 'Hybrid', 'variant' => 'secondary']],
                    'searchFocusChips' => [['label' => 'Symfony', 'variant' => 'primary']],
                ],
            ],
        ];

        $merged = CompanyCvSituationOverrideScope::mergeIntoPayload($base, $override);

        self::assertSame('Company', $merged[SituationContentContract::KEY_CONTENT_BY_LOCALE]['fr']['statusLabel']);
        self::assertSame('CV', $merged['title']);
    }
}
