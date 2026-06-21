<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Site;

use App\Entity\HomeCustomization;
use App\Service\Home\HomeCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Site\SiteMailTemplateDefaultContentService;
use App\Service\Site\SiteMailTemplateResolverService;
use App\Site\SiteMailTemplatesContract;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Unit tests for {@see SiteMailTemplateResolverService}.
 */
final class SiteMailTemplateResolverServiceTest extends TestCase
{
    /**
     * @brief Resolver uses stored custom subject and sender when present.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testResolveUsesStoredCustomSenderAndSubject(): void
    {
        $home = new HomeCustomization();
        $templates = SiteMailTemplatesContract::normalize(null);
        $templates['totp']['fromEmail'] = 'totp-custom@example.com';
        $templates['totp']['fromName'] = 'TOTP Sender';
        $templates['totp']['locales']['fr']['subject'] = 'Objet perso';
        $home->setMailTemplatesJson(SiteMailTemplatesContract::encodeForStorage($templates));

        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn($home);

        $defaultContent = $this->createMock(SiteMailTemplateDefaultContentService::class);
        $defaultContent->method('buildLocaleDefaults')->willReturn([
            'subject' => 'Default subject',
            'blocks' => [
                'title' => '<h2>Default title</h2>',
                'intro' => '<p>Default intro</p>',
                'expiry_hint' => '<p>Expiry</p>',
                'security_hint' => '<p>Security</p>',
                'footer' => '<p>Footer</p>',
            ],
            'labels' => [
                'brand' => 'Default brand',
                'code_label' => 'Code',
            ],
        ]);

        $localeConfiguration = $this->createMock(LocaleConfigurationService::class);
        $localeConfiguration->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'en',
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Translated fallback');

        $resolver = new SiteMailTemplateResolverService(
            $homeCustomizationService,
            $defaultContent,
            new RichHtmlSanitizer(),
            $translator,
            $localeConfiguration,
            'env-from@example.com',
            'owner@example.com',
            ['fr', 'en'],
            'fr',
        );

        $resolved = $resolver->resolve(SiteMailTemplatesContract::TYPE_TOTP, 'fr');

        self::assertSame('fr', $resolved['locale']);
        self::assertSame('totp-custom@example.com', $resolved['fromEmail']);
        self::assertSame('TOTP Sender', $resolved['fromName']);
        self::assertSame('Objet perso', $resolved['subject']);
    }

    /**
     * @brief Resolver falls back to environment sender when custom email is missing.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testResolveFallsBackToEnvFromEmail(): void
    {
        $home = new HomeCustomization();
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn($home);

        $defaultContent = $this->createMock(SiteMailTemplateDefaultContentService::class);
        $defaultContent->method('buildLocaleDefaults')->willReturn([
            'subject' => '',
            'blocks' => [
                'title' => '',
                'intro' => '',
                'expiry_hint' => '',
                'security_hint' => '',
                'footer' => '',
            ],
            'labels' => [],
        ]);

        $localeConfiguration = $this->createMock(LocaleConfigurationService::class);
        $localeConfiguration->method('getConfiguration')->willReturn([
            'activeLocales' => ['en'],
            'defaultLocale' => 'en',
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Fallback subject');

        $resolver = new SiteMailTemplateResolverService(
            $homeCustomizationService,
            $defaultContent,
            new RichHtmlSanitizer(),
            $translator,
            $localeConfiguration,
            'env-from@example.com',
            'owner@example.com',
            ['en'],
            'fr',
        );

        $resolved = $resolver->resolve(SiteMailTemplatesContract::TYPE_INVITATION, 'en');

        self::assertSame('env-from@example.com', $resolved['fromEmail']);
        self::assertSame('Fallback subject', $resolved['subject']);
    }

    /**
     * @brief Resolver exposes recruiter visit toEmail with environment fallback.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testResolveRecruiterVisitToEmailFallsBackToEnv(): void
    {
        $home = new HomeCustomization();
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn($home);

        $defaultContent = $this->createMock(SiteMailTemplateDefaultContentService::class);
        $defaultContent->method('buildLocaleDefaults')->willReturn([
            'subject' => 'Subject',
            'blocks' => [
                'title' => '',
                'intro' => '',
                'company_details' => '',
                'visit_summary' => '',
                'footer' => '',
            ],
            'labels' => [],
        ]);

        $localeConfiguration = $this->createMock(LocaleConfigurationService::class);
        $localeConfiguration->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr'],
            'defaultLocale' => 'fr',
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Fallback');

        $resolver = new SiteMailTemplateResolverService(
            $homeCustomizationService,
            $defaultContent,
            new RichHtmlSanitizer(),
            $translator,
            $localeConfiguration,
            'env-from@example.com',
            'owner@example.com',
            ['fr'],
            'fr',
        );

        $resolved = $resolver->resolve(SiteMailTemplatesContract::TYPE_RECRUITER_VISIT, 'fr');

        self::assertSame('owner@example.com', $resolved['toEmail']);
    }
}
