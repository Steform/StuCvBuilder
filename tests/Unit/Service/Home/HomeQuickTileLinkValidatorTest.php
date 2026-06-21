<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Home;

use App\Service\Home\HomeQuickTileLinkValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for home quick tile link validation.
 */
final class HomeQuickTileLinkValidatorTest extends TestCase
{
    private HomeQuickTileLinkValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new HomeQuickTileLinkValidator();
    }

    public function testAcceptsInternalPath(): void
    {
        self::assertSame('/dashboard', $this->validator->validateAndNormalize('/dashboard'));
    }

    public function testAcceptsHttpsUrl(): void
    {
        self::assertSame(
            'https://example.com/page',
            $this->validator->validateAndNormalize('https://example.com/page')
        );
    }

    public function testRejectsJavascriptScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateAndNormalize('javascript:alert(1)');
    }

    public function testRejectsProtocolRelativeUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateAndNormalize('//evil.test');
    }

    public function testSuggestsNewTabForExternalLinks(): void
    {
        self::assertTrue($this->validator->suggestsOpenInNewTab('https://example.com'));
        self::assertFalse($this->validator->suggestsOpenInNewTab('/cv'));
    }
}
