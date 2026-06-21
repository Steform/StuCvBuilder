<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Service\Cv\WebProfilesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @brief Unit tests for {@see WebProfilesContract}.
 *
 * @date 2026-06-09
 * @author Stephane H.
 */
final class WebProfilesContractTest extends TestCase
{
    /**
     * @brief Valid web profile URL must normalize.
     *
     * @return void
     * @date 2026-06-09
     * @author Stephane H.
     */
    public function testParseEntriesFromRequestAcceptsValidPayload(): void
    {
        $request = new Request([], [
            'web_profile_entries' => [
                [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'platform' => 'github',
                    'url' => 'https://github.com/example',
                    'visible' => '1',
                ],
            ],
        ]);

        $parsed = WebProfilesContract::parseEntriesFromRequest($request);
        self::assertIsArray($parsed);
        self::assertSame('github', $parsed[0]['platform']);
    }
}
