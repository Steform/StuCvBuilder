<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Site;

use App\Service\Site\SiteMailTemplateDefaultContentService;
use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Unit tests for {@see SiteMailTemplateDefaultContentService}.
 */
final class SiteMailTemplateDefaultContentServiceTest extends TestCase
{
    /**
     * @brief Default locale row is seeded from translation keys.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testBuildLocaleDefaultsUsesTranslator(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::atLeastOnce())
            ->method('trans')
            ->willReturnCallback(static function (string $id, array $parameters, ?string $domain, ?string $locale): string {
                return $id.'-'.$locale;
            });

        $service = new SiteMailTemplateDefaultContentService($translator);
        $defaults = $service->buildLocaleDefaults(SiteMailTemplatesContract::TYPE_TOTP, 'fr');

        self::assertSame('mail.totp.subject-fr', $defaults['subject']);
        self::assertStringContainsString('mail.totp.intro-fr', $defaults['blocks']['intro']);
        self::assertSame('mail.totp.brand-fr', $defaults['labels']['brand']);
    }
}
