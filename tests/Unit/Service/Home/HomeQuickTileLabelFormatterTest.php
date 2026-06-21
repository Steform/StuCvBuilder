<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Home;

use App\Entity\HomeQuickTile;
use App\Entity\HomeQuickTileTranslation;
use App\Service\Home\HomeQuickTileLabelFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see HomeQuickTileLabelFormatter}.
 */
final class HomeQuickTileLabelFormatterTest extends TestCase
{
    private HomeQuickTileLabelFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new HomeQuickTileLabelFormatter();
    }

    public function testFormatForStorageCapitalizesFirstLetter(): void
    {
        self::assertSame('Stockage', $this->formatter->formatForStorage('stockage'));
        self::assertSame('Équipe', $this->formatter->formatForStorage('équipe'));
    }

    public function testFormatForStorageReturnsEmptyForBlankInput(): void
    {
        self::assertSame('', $this->formatter->formatForStorage('   '));
    }

    public function testResolveForDisplayPrefersRequestLocale(): void
    {
        $tile = $this->tileWithLabels(['fr' => 'francais', 'en' => 'english']);

        self::assertSame('English', $this->formatter->resolveForDisplay($tile, 'en', 'fr'));
    }

    public function testResolveForDisplayFallsBackToDefaultLocale(): void
    {
        $tile = $this->tileWithLabels(['fr' => 'tableau de bord']);

        self::assertSame('Tableau de bord', $this->formatter->resolveForDisplay($tile, 'en', 'fr'));
    }

    public function testResolveForDisplayFallsBackToAnyAvailableTranslation(): void
    {
        $tile = $this->tileWithLabels(['de' => 'speicher']);

        self::assertSame('Speicher', $this->formatter->resolveForDisplay($tile, 'en', 'fr'));
    }

    /**
     * @param array<string, string> $labelsByLocale
     */
    private function tileWithLabels(array $labelsByLocale): HomeQuickTile
    {
        $tile = new HomeQuickTile();
        foreach ($labelsByLocale as $locale => $label) {
            $translation = new HomeQuickTileTranslation();
            $translation->setLocale($locale);
            $translation->setLabel($label);
            $tile->addTranslation($translation);
        }

        return $tile;
    }
}
