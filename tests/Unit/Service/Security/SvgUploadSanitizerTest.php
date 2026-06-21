<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Service\Security\SvgUploadSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see SvgUploadSanitizer}.
 */
final class SvgUploadSanitizerTest extends TestCase
{
    private SvgUploadSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new SvgUploadSanitizer();
    }

    /**
     * @brief Legitimate SVG markup must pass sanitization unchanged in structure.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testSanitizeAcceptsLegitimateSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path fill="#000"/></svg>';
        $sanitized = $this->sanitizer->sanitize($svg);

        self::assertStringContainsString('<path', $sanitized);
        self::assertStringNotContainsString('<script', strtolower($sanitized));
    }

    /**
     * @brief Malicious SVG payloads must be rejected.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testSanitizeRejectsScriptPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->sanitizer->sanitize('<svg><script>alert(1)</script></svg>');
    }

    /**
     * @brief Event handler attributes must be stripped from otherwise valid SVG.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testSanitizeStripsOnclickHandler(): void
    {
        $sanitized = $this->sanitizer->sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect onclick="alert(1)" width="10"/></svg>');

        self::assertStringNotContainsString('onclick', strtolower($sanitized));
    }
}
