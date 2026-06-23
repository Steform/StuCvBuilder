<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvEducationOverrideScope;
use App\Service\Cv\EducationContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvEducationOverrideScopeTest extends TestCase
{
    /**
     * @brief Merge applies education entries map onto base payload.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testMergeIntoPayloadReplacesEducationEntriesByLocale(): void
    {
        $base = [
            'title' => 'CV',
            EducationContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'sortOrder' => 0,
                    'startDate' => '2008-09',
                    'endDate' => '2010-06',
                    'isCurrent' => false,
                    'title' => 'Global diploma',
                    'institutionName' => 'Global School',
                    'institutionWebsiteUrl' => null,
                    'institutionLogoPath' => null,
                    'hideInstitutionName' => false,
                    'highlights' => ['Global highlight'],
                    'isPrimary' => true,
                ]],
            ],
        ];

        $override = [
            EducationContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '22222222-2222-4222-8222-222222222222',
                    'sortOrder' => 0,
                    'startDate' => '2010-09',
                    'endDate' => '2012-06',
                    'isCurrent' => false,
                    'title' => 'Company diploma',
                    'institutionName' => 'Target University',
                    'institutionWebsiteUrl' => 'https://example.com',
                    'institutionLogoPath' => null,
                    'hideInstitutionName' => false,
                    'highlights' => ['Tailored highlight'],
                    'isPrimary' => true,
                ]],
            ],
        ];

        $merged = CompanyCvEducationOverrideScope::mergeIntoPayload($base, $override);

        self::assertSame('Company diploma', $merged[EducationContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['title']);
        self::assertSame('CV', $merged['title']);
    }
}
