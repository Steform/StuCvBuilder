<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Service\Cv\SituationContentContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see SituationContentContract}.
 * @date 2026-05-20
 * @author Stephane H.
 */
final class SituationContentContractTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validRow(): array
    {
        return [
            'statusLabel' => 'DISPONIBLE',
            'introLead' => 'Intro text',
            'contractChip' => 'CDI',
            'searchWhereChipsDsl' => 'France:primary;Lituanie:secondary;Norvège:secondary',
            'searchModeChipsDsl' => 'Remote:secondary;Full remote:primary',
            'searchFocusChipsDsl' => 'COBOL:primary',
        ];
    }

    /**
     * @brief Valid DSL must parse with variants.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testParseChipListDslAcceptsValidSyntax(): void
    {
        $chips = SituationContentContract::parseChipListDsl('France:primary;Lituanie:secondary;B');

        self::assertIsArray($chips);
        self::assertCount(3, $chips);
        self::assertSame('France', $chips[0]['label']);
        self::assertSame('primary', $chips[0]['variant']);
        self::assertSame('secondary', $chips[2]['variant']);
    }

    /**
     * @brief Segment without variant defaults to secondary.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testParseChipListDslDefaultsVariantToSecondary(): void
    {
        $chips = SituationContentContract::parseChipListDsl('A:primary;B;C:secondary');

        self::assertIsArray($chips);
        self::assertSame('secondary', $chips[1]['variant']);
    }

    /**
     * @brief formatChipListToDsl round-trips normalized chips.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testFormatChipListToDslRoundTrip(): void
    {
        $normalized = SituationContentContract::normalizeContentRow($this->validRow());
        self::assertIsArray($normalized);

        $dsl = SituationContentContract::formatChipListToDsl($normalized['searchWhereChips']);
        self::assertStringContainsString('France:primary', $dsl);
        self::assertStringContainsString('Lituanie:secondary', $dsl);

        $reparsed = SituationContentContract::parseChipListDsl($dsl);
        self::assertIsArray($reparsed);
        self::assertCount(3, $reparsed);
    }

    /**
     * @brief More than twelve chips must be rejected.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testParseChipListDslRejectsTooManyChips(): void
    {
        $segments = [];
        for ($i = 1; $i <= 13; ++$i) {
            $segments[] = 'Chip'.$i.':secondary';
        }

        self::assertNull(SituationContentContract::parseChipListDsl(implode(';', $segments)));
    }

    /**
     * @brief Label containing colon must invalidate DSL.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testParseChipListDslRejectsColonInLabel(): void
    {
        self::assertNull(SituationContentContract::parseChipListDsl('Bad:label:primary'));
    }

    /**
     * @brief Legacy stored row migrates string geo chips and searchFocusChip.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testNormalizeContentRowMigratesLegacyFormat(): void
    {
        $normalized = SituationContentContract::normalizeContentRow([
            'statusLabel' => 'OK',
            'introLead' => 'Lead',
            'contractChip' => 'CDI',
            'searchWhereChips' => ['France', 'Lituanie'],
            'searchModeChips' => [
                ['label' => 'Remote', 'variant' => 'secondary'],
            ],
            'searchFocusChip' => 'COBOL',
        ]);

        self::assertIsArray($normalized);
        self::assertSame('secondary', $normalized['searchWhereChips'][0]['variant']);
        self::assertSame('COBOL', $normalized['searchFocusChips'][0]['label']);
        self::assertSame('primary', $normalized['searchFocusChips'][0]['variant']);
        self::assertArrayNotHasKey('locationLabel', $normalized);
        self::assertArrayNotHasKey('searchFocusChip', $normalized);
    }

    /**
     * @brief Parse request must accept DSL fields per locale.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testParseContentFromRequestWithDslFields(): void
    {
        $request = Request::create('/', 'POST', [
            'situation_content' => [
                'fr' => $this->validRow(),
            ],
        ]);

        $parsed = SituationContentContract::parseContentFromRequest($request, ['fr']);

        self::assertIsArray($parsed);
        self::assertArrayHasKey('fr', $parsed);
        self::assertCount(3, $parsed['fr']['searchWhereChips']);
    }

    /**
     * @brief Empty locale row is detectable after normalization fields removed.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testIsLocaleContentEmpty(): void
    {
        $normalized = SituationContentContract::normalizeContentRow($this->validRow());
        self::assertIsArray($normalized);
        self::assertFalse(SituationContentContract::isLocaleContentEmpty($normalized));

        $empty = [
            'statusLabel' => '',
            'introLead' => '',
            'contractChip' => '',
            'searchWhereChips' => [],
            'searchModeChips' => [],
            'searchFocusChips' => [],
        ];

        self::assertTrue(SituationContentContract::isLocaleContentEmpty($empty));
    }
}
