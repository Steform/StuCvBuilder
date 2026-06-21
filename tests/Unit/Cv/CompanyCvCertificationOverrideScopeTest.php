<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\CompanyCvCertificationOverrideScope;
use App\Service\Cv\CertificationContract;
use PHPUnit\Framework\TestCase;

final class CompanyCvCertificationOverrideScopeTest extends TestCase
{
    /**
     * @brief Merge applies canonical certification entries onto base payload.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testMergeIntoPayloadReplacesCertificationEntries(): void
    {
        $base = [
            'title' => 'CV',
            CertificationContract::KEY_ENTRIES => [[
                'id' => '11111111-1111-4111-8111-111111111111',
                'sortOrder' => 0,
                'startDate' => '2020-01',
                'endDate' => '2020-06',
                'isCurrent' => false,
                'titleByLocale' => ['fr' => 'Global certification'],
                'providerNameByLocale' => ['fr' => 'Global Provider'],
                'locationByLocale' => [],
                'providerWebsiteUrl' => null,
                'proofPdfPath' => null,
                'highlightsByLocale' => ['fr' => ['Global highlight']],
                'isPrimary' => true,
            ]],
        ];

        $override = [
            CertificationContract::KEY_ENTRIES => [[
                'id' => '22222222-2222-4222-8222-222222222222',
                'sortOrder' => 0,
                'startDate' => '2021-03',
                'endDate' => '2021-09',
                'isCurrent' => false,
                'titleByLocale' => ['fr' => 'Company certification'],
                'providerNameByLocale' => ['fr' => 'Target Academy'],
                'locationByLocale' => [],
                'providerWebsiteUrl' => 'https://example.com',
                'proofPdfPath' => null,
                'highlightsByLocale' => ['fr' => ['Tailored highlight']],
                'isPrimary' => true,
            ]],
        ];

        $merged = CompanyCvCertificationOverrideScope::mergeIntoPayload($base, $override);

        self::assertSame('Company certification', $merged[CertificationContract::KEY_ENTRIES][0]['titleByLocale']['fr']);
        self::assertSame('CV', $merged['title']);
        self::assertArrayNotHasKey(CertificationContract::KEY_ENTRIES_BY_LOCALE, $merged);
    }

    /**
     * @brief Legacy override map must migrate to canonical entries on sanitize.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testSanitizeForPersistenceMigratesLegacyOverrideMap(): void
    {
        $sanitized = CompanyCvCertificationOverrideScope::sanitizeForPersistence([
            CertificationContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [[
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'sortOrder' => 0,
                    'startDate' => '2020-01',
                    'endDate' => '2020-12',
                    'isCurrent' => false,
                    'title' => 'Legacy cert',
                    'providerName' => 'Legacy provider',
                    'highlights' => [],
                    'isPrimary' => true,
                ]],
            ],
        ], ['fr'], 'fr');

        self::assertArrayHasKey(CertificationContract::KEY_ENTRIES, $sanitized);
        self::assertSame('Legacy cert', $sanitized[CertificationContract::KEY_ENTRIES][0]['titleByLocale']['fr']);
        self::assertArrayNotHasKey(CertificationContract::KEY_ENTRIES_BY_LOCALE, $sanitized);
    }
}
