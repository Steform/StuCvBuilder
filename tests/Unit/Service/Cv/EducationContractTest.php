<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\EducationContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see EducationContract} parsing and validation.
 * @date 2026-05-15
 * @author Stephane H.
 */
final class EducationContractTest extends TestCase
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
            'education_entries' => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'sortOrder' => '0',
                        'startDate' => '2018-01',
                        'endDate' => '2022-12',
                        'isCurrent' => '0',
                        'title' => 'Technicien',
                        'institutionName' => 'CKELPROCESS',
                        'institutionWebsiteUrl' => 'https://www.example.com/',
                        'location' => 'Villeurbanne, France',
                        'highlights' => ['Line one', ''],
                        'isPrimary' => '1',
                    ],
                ],
            ],
        ]);

        $parsed = EducationContract::parseEntriesFromRequest($request, ['fr']);
        self::assertIsArray($parsed);
        self::assertCount(1, $parsed['fr']);
        self::assertSame('Technicien', $parsed['fr'][0]['title']);
        self::assertSame('Villeurbanne, France', $parsed['fr'][0]['location']);
        self::assertSame(['Line one'], $parsed['fr'][0]['highlights']);
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
            'education_entries' => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'sortOrder' => '0',
                        'startDate' => '2018-01',
                        'endDate' => '2022-12',
                        'isCurrent' => ['0'],
                        'title' => 'Technicien',
                        'institutionName' => 'CKELPROCESS',
                        'institutionWebsiteUrl' => '',
                        'highlights' => ['Line one'],
                        'isPrimary' => ['0'],
                    ],
                ],
            ],
        ]);

        $parsed = EducationContract::parseEntriesFromRequest($request, ['fr']);
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
        $entry = EducationContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2018-01',
            'endDate' => '2022-12',
            'title' => 'Legacy role',
            'institutionName' => 'Legacy Co',
            'highlights' => ['Detail'],
            'isVisible' => false,
        ], 0);

        self::assertIsArray($entry);
        self::assertFalse($entry['isPrimary']);
        self::assertArrayNotHasKey('isVisible', $entry);
    }

    /**
     * @brief isPrimary must stay consistent across locales for the same sortOrder slot.
     *
     * @return void
     * @date 2026-05-31
     * @author Stephane H.
     */
    public function testSyncIsPrimaryAcrossLocalesPropagatesSecondarySlot(): void
    {
        $synced = EducationContract::syncIsPrimaryAcrossLocales([
            'fr' => [
                ['sortOrder' => 2, 'isPrimary' => true, 'title' => 'FR role'],
            ],
            'en' => [
                ['sortOrder' => 2, 'isPrimary' => false, 'title' => 'EN role'],
            ],
        ]);

        self::assertFalse($synced['fr'][0]['isPrimary']);
        self::assertFalse($synced['en'][0]['isPrimary']);
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
            'education_entries' => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'startDate' => '2020-01',
                        'endDate' => '2019-01',
                        'title' => 'Role',
                        'institutionName' => 'Co',
                    ],
                ],
            ],
        ]);

        self::assertNull(EducationContract::parseEntriesFromRequest($request, ['fr']));
    }

    /**
     * @brief Current role may omit end date.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeEntryAllowsCurrentRoleWithoutEndDate(): void
    {
        $entry = EducationContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2024-06',
            'isCurrent' => true,
            'title' => 'Developer',
            'institutionName' => 'Acme',
            'highlights' => [],
        ], 0);

        self::assertIsArray($entry);
        self::assertTrue($entry['isCurrent']);
        self::assertNull($entry['endDate']);
    }

    /**
     * @brief Logo without institution name must be accepted when path is valid.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeEntryAllowsLogoWithoutInstitutionName(): void
    {
        $logoPath = EducationContract::EDUCATION_LOGO_PATH_PREFIX.'education-logo-test.webp';
        $entry = EducationContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2020-01',
            'endDate' => '2021-12',
            'title' => 'Role',
            'institutionName' => '',
            'highlights' => [],
        ], 0, $logoPath);

        self::assertIsArray($entry);
        self::assertSame('', $entry['institutionName']);
        self::assertSame($logoPath, $entry['institutionLogoPath']);
    }

    /**
     * @brief Empty logo and institution name must be rejected.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeEntryRejectsEmptyLogoAndName(): void
    {
        self::assertNull(EducationContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2020-01',
            'endDate' => '2021-12',
            'title' => 'Role',
            'institutionName' => '',
            'highlights' => [],
        ], 0));
    }

    /**
     * @brief hideInstitutionName defaults to false when absent.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testHideInstitutionNameDefaultsFalse(): void
    {
        $entry = EducationContract::normalizeEntry([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'startDate' => '2020-01',
            'endDate' => '2021-12',
            'title' => 'Role',
            'institutionName' => 'Acme',
            'highlights' => [],
        ], 0);

        self::assertIsArray($entry);
        self::assertFalse($entry['hideInstitutionName']);
    }

    /**
     * @brief Invalid stored logo path prefix must be rejected.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testNormalizeStoredLogoPathRejectsInvalidPrefix(): void
    {
        self::assertNull(EducationContract::normalizeStoredLogoPath('images/cv/about/custom/x.webp'));
    }
}
