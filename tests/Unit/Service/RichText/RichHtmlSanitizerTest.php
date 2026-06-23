<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\RichText;

use App\Service\RichText\RichHtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see RichHtmlSanitizer}.
 * @date 2026-05-16
 * @author Stephane H.
 */
final class RichHtmlSanitizerTest extends TestCase
{
    private RichHtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new RichHtmlSanitizer();
    }

    /**
     * @brief Structural-only CKEditor output must count as empty for default skeleton fallback.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testIsEffectivelyEmptyDetectsBlankEditorMarkup(): void
    {
        self::assertTrue($this->sanitizer->isEffectivelyEmpty(''));
        self::assertTrue($this->sanitizer->isEffectivelyEmpty('<p></p>'));
        self::assertTrue($this->sanitizer->isEffectivelyEmpty('<p><br></p>'));
        self::assertTrue($this->sanitizer->isEffectivelyEmpty('<p>&nbsp;</p>'));
        self::assertFalse($this->sanitizer->isEffectivelyEmpty('<p>Hello</p>'));
        self::assertFalse($this->sanitizer->isEffectivelyEmpty('<h1>[[cv.display_name]]</h1>'));
    }

    /**
     * @brief Lowercase h2/h3 in stored HTML must be normalized to a leading capital letter.
     *
     * @return void
     * @date 2026-05-16
     * @author Stephane H.
     */
    public function testCapitalizePresentationHeadingFirstLettersUppercasesH2AndH3(): void
    {
        $html = '<h2>ce que vous cherchez</h2><h3>votre situation actuelle</h3>';
        $out = $this->sanitizer->capitalizePresentationHeadingFirstLetters($html);

        self::assertStringContainsString('<h2>Ce que vous cherchez</h2>', $out);
        self::assertStringContainsString('<h3>Votre situation actuelle</h3>', $out);
    }

    /**
     * @brief Known XSS payloads must be stripped from sanitized HTML output.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testSanitizeStripsKnownXssPayloads(): void
    {
        $payloads = [
            '<p>Hello<script>alert(1)</script></p>',
            '<img src=x onerror=alert(1)>',
            '<a href="javascript:alert(1)">click</a>',
        ];

        foreach ($payloads as $payload) {
            $sanitized = $this->sanitizer->sanitize($payload);
            self::assertStringNotContainsString('<script', strtolower($sanitized));
            self::assertStringNotContainsString('onerror=', strtolower($sanitized));
            self::assertStringNotContainsString('javascript:', strtolower($sanitized));
        }
    }
}
