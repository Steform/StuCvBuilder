<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Site;

use App\Entity\HomeCustomization;
use App\Service\Home\HomeCustomizationService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Site\SiteMailTemplateAdminService;
use App\Service\Site\SiteMailTemplateDefaultContentService;
use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Unit tests for {@see SiteMailTemplateAdminService}.
 */
final class SiteMailTemplateAdminServiceTest extends TestCase
{
    /**
     * @brief Admin save sanitizes rich HTML blocks before persistence.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testApplyFromAdminRequestSanitizesRichBlocks(): void
    {
        $home = new HomeCustomization();
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn($home);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Default');
        $defaultContent = new SiteMailTemplateDefaultContentService($translator);

        $service = new SiteMailTemplateAdminService(
            $homeCustomizationService,
            new RichHtmlSanitizer(),
            $defaultContent,
        );

        $request = new Request([], [
            'mail_templates' => [
                'totp' => [
                    'locales' => [
                        'fr' => [
                            'blocks' => [
                                'intro' => '<p>Safe</p><script>alert(1)</script>',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $service->applyFromAdminRequest($request, ['fr']);

        $stored = SiteMailTemplatesContract::decodeFromStorage($home->getMailTemplatesJson());
        self::assertStringContainsString('<p>Safe</p>', $stored['totp']['locales']['fr']['blocks']['intro']);
        self::assertStringNotContainsString('<script>', $stored['totp']['locales']['fr']['blocks']['intro']);
    }

    /**
     * @brief Invalid from email triggers validation exception.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testApplyFromAdminRequestRejectsInvalidFromEmail(): void
    {
        $home = new HomeCustomization();
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn($home);

        $translator = $this->createMock(TranslatorInterface::class);
        $defaultContent = new SiteMailTemplateDefaultContentService($translator);

        $service = new SiteMailTemplateAdminService(
            $homeCustomizationService,
            new RichHtmlSanitizer(),
            $defaultContent,
        );

        $request = new Request([], [
            'mail_templates' => [
                'totp' => [
                    'from_email' => 'invalid-email',
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $service->applyFromAdminRequest($request, ['fr']);
    }
}
