<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvExperienceOverrideScope;
use App\Service\Cv\ExperienceContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvExperienceOverrideScopeTest extends TestCase
{
    /**
     * @brief Merge applies experience entries map onto base payload.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testMergeIntoPayloadReplacesExperienceEntriesByLocale(): void
    {
        $base = [
            'title' => 'CV',
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'sortOrder' => 0,
                    'startDate' => '2020-01',
                    'endDate' => '2021-12',
                    'isCurrent' => false,
                    'title' => 'Global role',
                    'companyName' => 'Global Co',
                    'companyWebsiteUrl' => null,
                    'companyLogoPath' => null,
                    'hideCompanyName' => false,
                    'highlights' => ['Did global work'],
                    'isPrimary' => true,
                ]],
            ],
        ];

        $override = [
            ExperienceContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '22222222-2222-4222-8222-222222222222',
                    'sortOrder' => 0,
                    'startDate' => '2022-01',
                    'endDate' => null,
                    'isCurrent' => true,
                    'title' => 'Company role',
                    'companyName' => 'Target Co',
                    'companyWebsiteUrl' => 'https://example.com',
                    'companyLogoPath' => null,
                    'hideCompanyName' => false,
                    'highlights' => ['Tailored highlight'],
                    'isPrimary' => true,
                ]],
            ],
        ];

        $merged = CompanyCvExperienceOverrideScope::mergeIntoPayload($base, $override);

        self::assertSame('Company role', $merged[ExperienceContract::KEY_ENTRIES_BY_LOCALE]['fr'][0]['title']);
        self::assertSame('CV', $merged['title']);
    }
}
