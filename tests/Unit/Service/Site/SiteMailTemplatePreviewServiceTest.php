<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Site;

use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Site\SiteMailTemplateDefaultContentService;
use App\Service\Site\SiteMailTemplatePreviewService;
use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @brief Unit tests for {@see SiteMailTemplatePreviewService}.
 */
final class SiteMailTemplatePreviewServiceTest extends TestCase
{
    /**
     * @brief Preview rejects unknown template types.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testRenderFromAdminPreviewRequestRejectsInvalidType(): void
    {
        $service = $this->createService();

        $request = new Request([], [
            'mail_template_preview_type' => 'unknown',
            'mail_template_preview_locale' => 'fr',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dashboard.configuration_site.mail_templates.flash.invalid_type');

        $service->renderFromAdminPreviewRequest($request, ['fr']);
    }

    /**
     * @brief Preview rejects locales outside active locale list.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testRenderFromAdminPreviewRequestRejectsInvalidLocale(): void
    {
        $service = $this->createService();

        $request = new Request([], [
            'mail_template_preview_type' => SiteMailTemplatesContract::TYPE_TOTP,
            'mail_template_preview_locale' => 'xx',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dashboard.configuration_site.mail_templates.preview.invalid_locale');

        $service->renderFromAdminPreviewRequest($request, ['fr']);
    }

    /**
     * @brief Preview renders draft subject and HTML for one template locale.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testRenderFromAdminPreviewRequestUsesDraftValues(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('emails/totp_code.html.twig', self::callback(static function (array $context): bool {
                return ($context['totpCode'] ?? null) === '123456'
                    && ($context['locale'] ?? null) === 'fr';
            }))
            ->willReturn('<html>preview</html>');

        $service = $this->createService($twig);

        $request = new Request([], [
            'mail_template_preview_type' => SiteMailTemplatesContract::TYPE_TOTP,
            'mail_template_preview_locale' => 'fr',
            'mail_templates' => [
                'totp' => [
                    'from_email' => 'custom-from@example.com',
                    'from_name' => 'Custom Sender',
                    'locales' => [
                        'fr' => [
                            'subject' => 'Objet test',
                            'blocks' => [
                                'intro' => '<p>Intro perso</p>',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $preview = $service->renderFromAdminPreviewRequest($request, ['fr']);

        self::assertSame(SiteMailTemplatesContract::TYPE_TOTP, $preview['type']);
        self::assertSame('fr', $preview['locale']);
        self::assertSame('custom-from@example.com', $preview['fromEmail']);
        self::assertSame('Custom Sender', $preview['fromName']);
        self::assertNull($preview['toEmail']);
        self::assertSame('Objet test', $preview['subject']);
        self::assertSame('<html>preview</html>', $preview['html']);
    }

    /**
     * @brief Build preview service with mocked collaborators.
     *
     * @param Environment|null $twig Optional Twig renderer mock.
     * @return SiteMailTemplatePreviewService
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function createService(?Environment $twig = null): SiteMailTemplatePreviewService
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => match ($id) {
                'dashboard.configuration_site.mail_templates.preview.sample.totp_code' => '123456',
                default => 'Translated',
            },
        );

        $defaultContent = new SiteMailTemplateDefaultContentService($translator);

        if ($twig === null) {
            $twig = $this->createMock(Environment::class);
            $twig->method('render')->willReturn('<html>preview</html>');
        }

        return new SiteMailTemplatePreviewService(
            $defaultContent,
            new RichHtmlSanitizer(),
            $twig,
            $translator,
            'env-from@example.com',
            'owner@example.com',
        );
    }
}
