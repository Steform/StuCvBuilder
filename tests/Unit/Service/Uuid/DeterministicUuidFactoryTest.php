<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Uuid;

use App\Service\Uuid\DeterministicUuidFactory;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see DeterministicUuidFactory}.
 */
final class DeterministicUuidFactoryTest extends TestCase
{
    /**
     * @brief Same domain and seed must always produce the same UUID.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testGenerateIsStableForSameSeed(): void
    {
        $first = DeterministicUuidFactory::generate('cv-education', 'seed-a');
        $second = DeterministicUuidFactory::generate('cv-education', 'seed-a');

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $first
        );
    }

    /**
     * @brief Different seeds must produce different UUIDs within the same domain.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testGenerateDiffersForDifferentSeeds(): void
    {
        $first = DeterministicUuidFactory::generate('cv-education', 'seed-a');
        $second = DeterministicUuidFactory::generate('cv-education', 'seed-b');

        self::assertNotSame($first, $second);
    }
}
