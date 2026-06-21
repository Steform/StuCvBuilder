<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Service\Cv\CvSituationContentSettingsService;
use App\Service\Cv\SituationContentContract;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @brief Unit tests for {@see CvSituationContentSettingsService}.
 * @date 2026-05-20
 * @author Stephane H.
 */
final class CvSituationContentSettingsServiceTest extends KernelTestCase
{
    private CvSituationContentSettingsService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(CvSituationContentSettingsService::class);
        self::assertInstanceOf(CvSituationContentSettingsService::class, $service);
        $this->service = $service;
    }

    /**
     * @brief Missing map must yield generic placeholder content without legacy business copy.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testResolveUsesPlaceholderWhenMapMissing(): void
    {
        $resolved = $this->service->resolveFromContentJson('{}', ['fr', 'en'], 'fr', 'fr');

        self::assertFalse($resolved['hasPersistedMap']);
        self::assertNotSame('', $resolved['content']['introLead'] ?? '');
        self::assertSame([], $resolved['content']['searchFocusChips'] ?? null);
        self::assertSame([], $resolved['content']['searchWhereChips'] ?? null);
        self::assertSame([], $resolved['content']['searchModeChips'] ?? null);
        self::assertSame('', $resolved['content']['statusLabel'] ?? null);
        self::assertSame('', $resolved['content']['contractChip'] ?? null);
        self::assertArrayNotHasKey('locationLabel', $resolved['content']);
        self::assertSame('', $resolved['contentByLocale']['fr']['searchWhereChipsDsl'] ?? '');
    }

    /**
     * @brief Placeholder builder must not inject COBOL or country-specific fallback chips.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testBuildPlaceholderContentForLocaleHasNoLegacyBusinessCopy(): void
    {
        $content = $this->service->buildPlaceholderContentForLocale('fr');
        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE);

        self::assertIsString($encoded);
        self::assertStringNotContainsString('COBOL', $encoded);
        self::assertStringNotContainsString('France', $encoded);
        self::assertStringNotContainsString('Lituanie', $encoded);
        self::assertStringNotContainsString('Norvège', $encoded);
        self::assertStringContainsString('administration', $content['introLead'] ?? '');
    }

    /**
     * @brief Persisted locale row must override placeholder and expose DSL fields.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testResolveUsesStoredContentForLocale(): void
    {
        $stored = [
            SituationContentContract::KEY_CONTENT_BY_LOCALE => [
                'de' => [
                    'statusLabel' => 'VERFÜGBAR',
                    'introLead' => 'German intro only',
                    'contractChip' => 'CDI',
                    'searchWhereChipsDsl' => 'France:primary;Deutschland:secondary',
                    'searchModeChipsDsl' => 'Remote:secondary;Full remote:primary',
                    'searchFocusChipsDsl' => 'COBOL:primary;SQL:secondary',
                ],
            ],
        ];

        $resolved = $this->service->resolveFromContentJson(
            (string) json_encode($stored, JSON_UNESCAPED_UNICODE),
            ['fr', 'de'],
            'fr',
            'de'
        );

        self::assertTrue($resolved['hasPersistedMap']);
        self::assertSame('German intro only', $resolved['content']['introLead']);
        self::assertCount(2, $resolved['content']['searchFocusChips']);
        self::assertStringContainsString('COBOL:primary', $resolved['contentByLocale']['de']['searchFocusChipsDsl']);
    }
}
