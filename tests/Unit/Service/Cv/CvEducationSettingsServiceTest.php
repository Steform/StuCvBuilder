<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\CvEducationSettingsService;
use App\Service\Cv\EducationContract;
use App\Tests\Support\CvPdfPlaceholderTestTranslator;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CvEducationSettingsService}.
 * @date 2026-05-15
 * @author Stephane H.
 */
final class CvEducationSettingsServiceTest extends TestCase
{
    private CvEducationSettingsService $service;

    protected function setUp(): void
    {
        $this->service = new CvEducationSettingsService(
            CvPdfPlaceholderTestTranslator::create(),
        );
    }

    /**
     * @brief Empty JSON without persisted map must yield a generic placeholder row.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testResolveFromContentJsonPlaceholderWhenMapMissing(): void
    {
        $resolved = $this->service->resolveFromContentJson('{}', ['fr'], 'fr', 'fr');

        self::assertFalse($resolved['hasPersistedMap']);
        self::assertCount(1, $resolved['entries']);
        self::assertStringContainsString('cv.placeholder.education.title', (string) ($resolved['entries'][0]['title'] ?? ''));
    }

    /**
     * @brief Persisted empty map must keep public timeline rows empty.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveFromContentJsonReturnsEmptyWhenMapPersistedButEmpty(): void
    {
        $payload = [
            EducationContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [],
            ],
        ];

        $resolved = $this->service->resolveFromContentJson(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            ['fr'],
            'fr',
            'fr'
        );

        self::assertTrue($resolved['hasPersistedMap']);
        self::assertSame([], $resolved['entries']);
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
     * @brief Persisted map in JSON disables placeholder rows for empty locale list.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testResolveUsesPersistedMapWhenPresent(): void
    {
        $payload = [
            EducationContract::KEY_ENTRIES_BY_LOCALE => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'sortOrder' => 0,
                        'startDate' => '2020-01',
                        'endDate' => '2021-12',
                        'isCurrent' => false,
                        'title' => 'Custom diploma',
                        'institutionName' => 'Custom school',
                        'institutionWebsiteUrl' => null,
                        'highlights' => [],
                        'isPrimary' => true,
                    ],
                ],
            ],
        ];

        $resolved = $this->service->resolveFromContentJson((string) json_encode($payload), ['fr'], 'fr', 'fr');
        self::assertTrue($resolved['hasPersistedMap']);
        self::assertSame('Custom diploma', $resolved['entries'][0]['title']);
    }

    /**
     * @brief Admin preview map must filter primary visible entries per locale and detect secondary rows.
     * @return void
     * @date 2026-05-15
     * @author Stephane H.
     */
    public function testBuildAdminPreviewPayloadByLocaleFiltersPerLocale(): void
    {
        $entriesByLocale = [
            'fr' => [
                ['sortOrder' => 0, 'isPrimary' => true, 'title' => 'FR primary'],
                ['sortOrder' => 1, 'isPrimary' => false, 'title' => 'FR secondary'],
            ],
            'en' => [
                ['sortOrder' => 0, 'isPrimary' => true, 'title' => 'EN primary'],
            ],
        ];

        $preview = $this->service->buildAdminPreviewPayloadByLocale($entriesByLocale);

        self::assertCount(1, $preview['fr']['entries']);
        self::assertSame('FR primary', $preview['fr']['entries'][0]['title']);
        self::assertTrue($preview['fr']['hasSecondaryVisible']);
        self::assertCount(1, $preview['en']['entries']);
        self::assertSame('EN primary', $preview['en']['entries'][0]['title']);
        self::assertFalse($preview['en']['hasSecondaryVisible']);
    }

    /**
     * @brief Full education page payload must mark secondary-only rows for highlight styling.
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
}
