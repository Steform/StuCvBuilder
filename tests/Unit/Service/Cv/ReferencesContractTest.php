<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\ReferencesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see ReferencesContract}.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
final class ReferencesContractTest extends TestCase
{
    /**
     * @brief Section enabled checkbox must parse as true.
     *
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function testParseSectionEnabledFromRequest(): void
    {
        $request = new Request([], ['references_section_enabled' => '1']);
        self::assertTrue(ReferencesContract::parseSectionEnabledFromRequest($request));
    }

    /**
     * @brief Valid reference row must normalize per locale.
     *
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function testParseEntriesFromRequestAcceptsValidPayload(): void
    {
        $request = new Request([], [
            'reference_entries' => [
                'fr' => [
                    [
                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                        'name' => 'Jane Doe',
                        'contactMode' => 'on_request',
                    ],
                ],
            ],
        ]);

        $parsed = ReferencesContract::parseEntriesFromRequest($request, ['fr']);
        self::assertIsArray($parsed);
        self::assertSame('Jane Doe', $parsed['fr'][0]['name']);
    }
}
