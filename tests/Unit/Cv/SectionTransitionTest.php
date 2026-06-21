<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cv;

use App\Cv\SectionTransition;
use App\Cv\SectionTransitionContract;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for section transition enum and contract.
 *
 * @date 2026-05-20
 * @author Stephane H.
 */
final class SectionTransitionTest extends TestCase
{
    /**
     * @brief Valid stored values must resolve to matching cases.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testFromStoredAcceptsValidValues(): void
    {
        self::assertSame(SectionTransition::FadeVertical, SectionTransition::fromStored('fade_vertical'));
        self::assertSame(SectionTransition::OverlapSoft, SectionTransition::fromStored('overlap_soft'));
    }

    /**
     * @brief Invalid or missing values must fall back to fade_vertical.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testFromStoredFallsBackToDefault(): void
    {
        self::assertSame(SectionTransition::FadeVertical, SectionTransition::fromStored(null));
        self::assertSame(SectionTransition::FadeVertical, SectionTransition::fromStored('invalid'));
    }

    /**
     * @brief Admin must expose all six transitions.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testCasesForAdminIncludesAllTransitions(): void
    {
        self::assertCount(6, SectionTransition::casesForAdmin());
    }

    /**
     * @brief normalizeMap must return all eligible section keys.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testNormalizeMapReturnsEligibleKeys(): void
    {
        $map = SectionTransitionContract::normalizeMap(['situation' => 'none', 'experience' => 'bridge_band']);

        self::assertSame('none', $map['situation']);
        self::assertSame('bridge_band', $map['experience']);
        self::assertSame('fade_vertical', $map['skills']);
        self::assertCount(count(SectionTransitionContract::ELIGIBLE_SECTION_KEYS), $map);
    }

    /**
     * @brief mergeSubmittedIntoPayload must update only provided keys.
     *
     * @return void
     * @date 2026-05-20
     * @author Stephane H.
     */
    public function testMergeSubmittedIntoPayload(): void
    {
        $payload = SectionTransitionContract::mergeSubmittedIntoPayload(
            ['sectionTransitions' => ['situation' => 'fade_strong', 'skills' => 'none']],
            ['situation' => 'fade_short']
        );

        $map = $payload['sectionTransitions'];
        self::assertIsArray($map);
        self::assertSame('fade_short', $map['situation']);
        self::assertSame('none', $map['skills']);
    }
}
