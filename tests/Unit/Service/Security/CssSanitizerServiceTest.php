<?php

namespace App\Tests\Unit\Service\Security;

use App\Service\Security\CssSanitizerService;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for CssSanitizerService.
 * @date 2026-05-08
 * @author Stephane H.
 */
class CssSanitizerServiceTest extends TestCase
{
    /**
     * @brief Test that safe declarations are preserved.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testPreservesSafeDeclarations(): void
    {
        $s = new CssSanitizerService();
        $in = 'color: #fff; font-weight: 700; margin-top: 1rem;';
        $out = $s->sanitizeDeclarationBlock($in);
        $this->assertStringContainsString('color: #fff', $out);
        $this->assertStringContainsString('font-weight: 700', $out);
        $this->assertMatchesRegularExpression('/color:\s*#fff\s*;/', $out);
        $this->assertMatchesRegularExpression('/font-weight:\s*700\s*;/', $out);
    }

    /**
     * @brief Test that expression() payloads are removed.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testStripsExpression(): void
    {
        $s = new CssSanitizerService();
        $in = 'color: red; width: expression(alert(1));';
        $out = $s->sanitizeDeclarationBlock($in);
        $this->assertStringNotContainsString('expression', $out);
        $this->assertStringContainsString('color: red', $out);
    }

    /**
     * @brief Test that javascript URL payloads are removed.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testStripsJavascriptUrl(): void
    {
        $s = new CssSanitizerService();
        $in = "background-image: url('javascript:alert(1)'); color: blue;";
        $out = $s->sanitizeDeclarationBlock($in);
        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringContainsString('color: blue', $out);
    }

    /**
     * @brief Test that unknown properties are stripped.
     * @return void
     * @date 2026-05-08
     * @author Stephane H.
     */
    public function testStripsUnknownProperty(): void
    {
        $s = new CssSanitizerService();
        $in = '-moz-binding: url(x); color: green;';
        $out = $s->sanitizeDeclarationBlock($in);
        $this->assertStringNotContainsString('binding', $out);
        $this->assertStringContainsString('color: green', $out);
    }
}
