<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\ExperienceContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see ExperienceContract} parsing and validation.
 * @date 2026-05-15
 * @author Stephane H.
 */
final class ExperienceContractTest extends TestCase
{
    /**
     * @brief Valid request payload must normalize to structured entries.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testParseEntriesFromRequestAcceptsValidPayload(): void
    {
        $request = new Request([], [
            'experience_entries' => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'sortOrder' => '0',
                        'startDate' => '2018-01',
                        'endDate' => '2022-12',
                        'isCurrent' => '0',
                        'title' => 'Technicien',
                        'companyName' => 'CKELPROCESS',
                        'companyWebsiteUrl' => 'https://www.example.com/',
                        'location' => 'Lyon, France',
                        'highlights' => ['Line one', ''],
                        'isPrimary' => '1',
                    ],
                ],
            ],
        ]);

        $parsed = ExperienceContract::parseEntriesFromRequest($request, ['fr']);
        self::assertIsArray($parsed);
        self::assertCount(1, $parsed['fr']);
        self::assertSame('Technicien', $parsed['fr'][0]['title']);
        self::assertSame('Lyon, France', $parsed['fr'][0]['location']);
        self::assertSame(['Line one'], $parsed['fr'][0]['highlights']);
        self::assertSame('', $parsed['fr'][0]['detailHtml']);
    }

    /**
     * @brief Optional location must normalize to an empty string when absent.
     *
     * @return void
     * @date 2026-06-08
     * @author Stephane H.
     */
    public function testNormalizeEntryPersistsEmptyLocationWhenAbsent(): void
    {
        $entry = ExperienceContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2018-01',
            'endDate' => '2022-12',
            'isCurrent' => false,
            'title' => 'Technicien',
            'companyName' => 'ACME',
        ], 0);

        self::assertIsArray($entry);
        self::assertSame('', $entry['location']);
    }

    /**
     * @brief January start dates must render with month in period labels (2018/01, not 2018).
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testFormatYearMonthForDisplayAlwaysIncludesMonth(): void
    {
        self::assertSame('2018/01', ExperienceContract::formatYearMonthForDisplay('2018-01'));
        self::assertSame('2022/12', ExperienceContract::formatYearMonthForDisplay('2022-12'));
        self::assertSame('invalid', ExperienceContract::formatYearMonthForDisplay('invalid'));
    }

    /**
     * @brief Entries must be sorted by start date descending with current roles first.
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testSortEntriesChronologicallyForLocaleOrdersByDates(): void
    {
        $sorted = ExperienceContract::sortEntriesChronologicallyForLocale([
            [
                'id' => '11111111-1111-4111-8111-111111111111',
                'startDate' => '2014-01',
                'endDate' => '2017-12',
                'isCurrent' => false,
                'sortOrder' => 5,
            ],
            [
                'id' => '22222222-2222-4222-8222-222222222222',
                'startDate' => '2023-06',
                'endDate' => null,
                'isCurrent' => true,
                'sortOrder' => 1,
            ],
            [
                'id' => '33333333-3333-4333-8333-333333333333',
                'startDate' => '2018-01',
                'endDate' => '2022-12',
                'isCurrent' => false,
                'sortOrder' => 0,
            ],
        ]);

        self::assertSame('22222222-2222-4222-8222-222222222222', $sorted[0]['id']);
        self::assertSame(0, $sorted[0]['sortOrder']);
        self::assertSame('33333333-3333-4333-8333-333333333333', $sorted[1]['id']);
        self::assertSame('11111111-1111-4111-8111-111111111111', $sorted[2]['id']);
    }

    /**
     * @brief Overlapping month ranges must be accepted during normalization.
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testNormalizeEntriesByLocaleAcceptsOverlappingPeriods(): void
    {
        $entryIdA = '550e8400-e29b-41d4-a716-446655440000';
        $entryIdB = '660e8400-e29b-41d4-a716-446655440001';
        $rows = [
            'fr' => [
                [
                    'id' => $entryIdA,
                    'startDate' => '2018-01',
                    'endDate' => '2020-12',
                    'title' => 'Role A',
                    'companyName' => 'Co A',
                    'highlights' => [],
                    'isPrimary' => true,
                ],
                [
                    'id' => $entryIdB,
                    'startDate' => '2020-06',
                    'endDate' => '2022-12',
                    'title' => 'Role B',
                    'companyName' => 'Co B',
                    'highlights' => [],
                    'isPrimary' => true,
                ],
            ],
        ];

        $status = ExperienceContract::normalizeEntriesByLocaleWithStatus($rows);

        self::assertNull($status['error']);
        self::assertIsArray($status['entries']);
        self::assertCount(2, $status['entries']['fr']);
        self::assertTrue(ExperienceContract::entriesOverlap($rows['fr'][0], $rows['fr'][1]));
    }

    /**
     * @brief Rich-text detail HTML must be normalized on experience entries.
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testNormalizeEntryStoresDetailHtml(): void
    {
        $entry = ExperienceContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2018-01',
            'endDate' => '2022-12',
            'title' => 'Engineer',
            'companyName' => 'Acme',
            'highlights' => [],
            'detailHtml' => '<p>Built <strong>platforms</strong>.</p>',
            'isPrimary' => true,
        ], 0);

        self::assertIsArray($entry);
        self::assertSame('<p>Built <strong>platforms</strong>.</p>', $entry['detailHtml']);
    }

    /**
     * @brief Unchecked primary checkbox must persist as false via hidden-field POST values.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testParseEntriesFromRequestPersistsUncheckedPrimaryFlag(): void
    {
        $request = new Request([], [
            'experience_entries' => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'sortOrder' => '0',
                        'startDate' => '2018-01',
                        'endDate' => '2022-12',
                        'isCurrent' => ['0'],
                        'title' => 'Technicien',
                        'companyName' => 'CKELPROCESS',
                        'companyWebsiteUrl' => '',
                        'highlights' => ['Line one'],
                        'isPrimary' => ['0'],
                    ],
                ],
            ],
        ]);

        $parsed = ExperienceContract::parseEntriesFromRequest($request, ['fr']);
        self::assertIsArray($parsed);
        self::assertFalse($parsed['fr'][0]['isPrimary']);
        self::assertArrayNotHasKey('isVisible', $parsed['fr'][0]);
    }

    /**
     * @brief Legacy isVisible false must map to secondary placement when isPrimary is absent.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testNormalizeEntryMapsLegacyHiddenVisibleToSecondary(): void
    {
        $entry = ExperienceContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2018-01',
            'endDate' => '2022-12',
            'title' => 'Legacy role',
            'companyName' => 'Legacy Co',
            'highlights' => ['Detail'],
            'isVisible' => false,
        ], 0);

        self::assertIsArray($entry);
        self::assertFalse($entry['isPrimary']);
        self::assertArrayNotHasKey('isVisible', $entry);
    }

    /**
     * @brief isPrimary must stay consistent across locales for the same entry id.
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testSyncIsPrimaryAcrossLocalesPropagatesSecondarySlot(): void
    {
        $entryId = '550e8400-e29b-41d4-a716-446655440000';
        $synced = ExperienceContract::syncIsPrimaryAcrossLocales([
            'fr' => [
                ['id' => $entryId, 'sortOrder' => 2, 'isPrimary' => true, 'title' => 'FR role'],
            ],
            'en' => [
                ['id' => $entryId, 'sortOrder' => 2, 'isPrimary' => false, 'title' => 'EN role'],
            ],
        ]);

        self::assertFalse($synced['fr'][0]['isPrimary']);
        self::assertFalse($synced['en'][0]['isPrimary']);
    }

    /**
     * @brief Structural fields must be unified across locales for the same entry id.
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testSyncSharedEntryFieldsAcrossLocalesUsesCanonicalLocale(): void
    {
        $entryId = '660e8400-e29b-41d4-a716-446655440001';
        $synced = ExperienceContract::syncSharedEntryFieldsAcrossLocales([
            'fr' => [
                [
                    'id' => $entryId,
                    'sortOrder' => 0,
                    'companyName' => 'FR Company',
                    'companyWebsiteUrl' => 'https://fr.example',
                    'isPrimary' => true,
                    'title' => 'FR title',
                ],
            ],
            'en' => [
                [
                    'id' => $entryId,
                    'sortOrder' => 3,
                    'companyName' => 'EN Company',
                    'companyWebsiteUrl' => 'https://en.example',
                    'isPrimary' => false,
                    'title' => 'EN title',
                ],
            ],
        ]);

        self::assertSame('FR Company', $synced['en'][0]['companyName']);
        self::assertSame('https://fr.example', $synced['en'][0]['companyWebsiteUrl']);
        self::assertSame(0, $synced['en'][0]['sortOrder']);
        self::assertTrue($synced['en'][0]['isPrimary']);
        self::assertSame('EN title', $synced['en'][0]['title']);
    }

    /**
     * @brief Invalid end date before start date must be rejected.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testParseEntriesFromRequestRejectsInvalidDateRange(): void
    {
        $request = new Request([], [
            'experience_entries' => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'startDate' => '2020-01',
                        'endDate' => '2019-01',
                        'title' => 'Role',
                        'companyName' => 'Co',
                    ],
                ],
            ],
        ]);

        self::assertNull(ExperienceContract::parseEntriesFromRequest($request, ['fr']));
    }

    /**
     * @brief Current role may omit end date.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeEntryAllowsCurrentRoleWithoutEndDate(): void
    {
        $entry = ExperienceContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2024-06',
            'isCurrent' => true,
            'title' => 'Developer',
            'companyName' => 'Acme',
            'highlights' => [],
        ], 0);

        self::assertIsArray($entry);
        self::assertTrue($entry['isCurrent']);
        self::assertNull($entry['endDate']);
    }

    /**
     * @brief Logo without company name must be accepted when path is valid.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeEntryAllowsLogoWithoutCompanyName(): void
    {
        $logoPath = ExperienceContract::EXPERIENCE_LOGO_PATH_PREFIX.'experience-logo-test.webp';
        $entry = ExperienceContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2020-01',
            'endDate' => '2021-12',
            'title' => 'Role',
            'companyName' => '',
            'highlights' => [],
        ], 0, $logoPath);

        self::assertIsArray($entry);
        self::assertSame('', $entry['companyName']);
        self::assertSame($logoPath, $entry['companyLogoPath']);
    }

    /**
     * @brief Empty logo and company name must be rejected.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeEntryRejectsEmptyLogoAndName(): void
    {
        self::assertNull(ExperienceContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2020-01',
            'endDate' => '2021-12',
            'title' => 'Role',
            'companyName' => '',
            'highlights' => [],
        ], 0));
    }

    /**
     * @brief hideCompanyName defaults to false when absent.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testHideCompanyNameDefaultsFalse(): void
    {
        $entry = ExperienceContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2020-01',
            'endDate' => '2021-12',
            'title' => 'Role',
            'companyName' => 'Acme',
            'highlights' => [],
        ], 0);

        self::assertIsArray($entry);
        self::assertFalse($entry['hideCompanyName']);
    }

    /**
     * @brief Invalid stored logo path prefix must be rejected.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeStoredLogoPathRejectsInvalidPrefix(): void
    {
        self::assertNull(ExperienceContract::normalizeStoredLogoPath('images/cv/about/custom/x.webp'));
    }

    /**
     * @brief Missing locale rows must be rebuilt from existing translations for the same entry id.
     *
     * @return void
     * @date 2026-06-03
     * @author Stephane H.
     */
    public function testAlignEntriesAcrossActiveLocalesRestoresMissingLocaleRows(): void
    {
        $entryId = '550e8400-e29b-41d4-a716-446655440000';
        $enEntry = [
            'id' => $entryId,
            'sortOrder' => 0,
            'startDate' => '2020-01',
            'endDate' => '2022-12',
            'isCurrent' => false,
            'title' => 'Developer EN',
            'companyName' => 'Acme',
            'companyWebsiteUrl' => null,
            'companyLogoPath' => null,
            'hideCompanyName' => false,
            'highlights' => [],
            'detailHtml' => '<p>EN detail</p>',
            'isPrimary' => true,
        ];

        $aligned = ExperienceContract::alignEntriesAcrossActiveLocales(
            [
                'en' => [$enEntry],
            ],
            ['fr', 'en']
        );

        self::assertCount(1, $aligned['fr']);
        self::assertSame($entryId, $aligned['fr'][0]['id']);
        self::assertSame('', $aligned['fr'][0]['title']);
        self::assertSame('', $aligned['fr'][0]['detailHtml']);
        self::assertSame('2020-01', $aligned['fr'][0]['startDate']);
        self::assertCount(1, $aligned['en']);
        self::assertSame('Developer EN', $aligned['en'][0]['title']);
    }

    /**
     * @brief Locale rows with different UUIDs must remain separate experiences.
     *
     * @return void
     * @date 2026-06-04
     * @author Stephane H.
     */
    public function testAlignEntriesAcrossActiveLocalesKeepsDistinctEntryIds(): void
    {
        $sharedPeriod = [
            'sortOrder' => 0,
            'startDate' => '2018-01',
            'endDate' => '2022-12',
            'isCurrent' => false,
            'companyName' => 'CKELPROCESS',
            'companyWebsiteUrl' => null,
            'companyLogoPath' => null,
            'hideCompanyName' => false,
            'highlights' => [],
            'isPrimary' => true,
        ];

        $aligned = ExperienceContract::alignEntriesAcrossActiveLocales(
            [
                'fr' => [array_merge($sharedPeriod, [
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'title' => 'Technicien',
                    'detailHtml' => '<p>FR</p>',
                ])],
                'en' => [array_merge($sharedPeriod, [
                    'id' => '22222222-2222-4222-8222-222222222222',
                    'title' => 'Payment technician',
                    'detailHtml' => '<p>EN</p>',
                ])],
            ],
            ['fr', 'en']
        );

        self::assertCount(2, $aligned['fr']);
        self::assertCount(2, $aligned['en']);
        self::assertSame('11111111-1111-4111-8111-111111111111', $aligned['fr'][0]['id']);
        self::assertSame('22222222-2222-4222-8222-222222222222', $aligned['en'][1]['id']);
        self::assertSame('Technicien', $aligned['fr'][0]['title']);
        self::assertSame('Payment technician', $aligned['en'][1]['title']);
    }
}
