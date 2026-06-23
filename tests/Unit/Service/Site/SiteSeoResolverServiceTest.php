<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Site;

use App\Cv\SkillsTreeContract;
use App\Entity\CvProfile;
use App\Entity\HomeCustomization;
use App\Repository\CvProfileRepository;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvAgeYearsCalculator;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Cv\CvPublicIdentityPlaceholderService;
use App\Service\Employment\EmploymentDocumentStorageService;
use App\Service\Employment\EmploymentPublicDocumentPdfResolver;
use App\Service\Home\HomeCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Site\SiteSeoResolverService;
use App\Service\Site\SiteSitemapService;
use App\Tests\Support\CvPdfPlaceholderTestTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Unit tests for {@see SiteSeoResolverService} and {@see SiteSitemapService}.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class SiteSeoResolverServiceTest extends TestCase
{
    /**
     * @brief Build resolver service with optional profile payload and locale configuration.
     *
     * @param array<string, mixed> $profilePayload Persisted CV profile JSON payload.
     * @param list<string> $activeLocales Active locale codes.
     * @param string $defaultLocale Site default locale.
     * @param Request|null $request Current HTTP request for hreflang generation.
     * @return SiteSeoResolverService
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function createService(
        array $profilePayload = [],
        array $activeLocales = ['fr', 'en'],
        string $defaultLocale = 'fr',
        ?Request $request = null,
    ): SiteSeoResolverService {
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('resolveMetaDescriptionForLocale')->willReturn('');
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn(new HomeCustomization());
        $homeCustomizationService->method('resolveSignatureImageRelativePath')->willReturn(null);
        $homeCustomizationService->method('resolveOpenGraphImageRelativePath')->willReturn(null);
        $homeCustomizationService->method('isLargeFormatShareImage')->willReturn(false);
        $homeCustomizationService
            ->method('resolveSiteFaviconRelativePath')
            ->willReturn(HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH);

        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        if ($profilePayload !== []) {
            $profile = new CvProfile('Test CV', json_encode($profilePayload, JSON_THROW_ON_ERROR));
            $cvProfileRepository->method('findOneBy')->willReturn($profile);
        } else {
            $cvProfileRepository->method('findOneBy')->willReturn(null);
        }

        $translator = CvPdfPlaceholderTestTranslator::create();

        $identityPlaceholderService = $this->createIdentityPlaceholderService($translator);

        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => $activeLocales,
            'defaultLocale' => $defaultLocale,
        ]);

        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        return new SiteSeoResolverService(
            $homeCustomizationService,
            $cvProfileRepository,
            $translator,
            $identityPlaceholderService,
            $localeConfigurationService,
            $requestStack,
        );
    }

    /**
     * @brief Build placeholder service without mocking final employment PDF resolver.
     *
     * @param TranslatorInterface $translator Translator used by the placeholder service.
     * @return CvPublicIdentityPlaceholderService
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function createIdentityPlaceholderService(TranslatorInterface $translator): CvPublicIdentityPlaceholderService
    {
        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'fr',
        ]);

        return new CvPublicIdentityPlaceholderService(
            $translator,
            new RichHtmlSanitizer(),
            new CvAgeYearsCalculator(),
            new EmploymentPublicDocumentPdfResolver(
                $this->createMock(TrackedCompanyRepository::class),
                $this->createMock(EmploymentDocumentVariantRepository::class),
                $this->createMock(EmploymentDocumentStorageService::class),
                $localeConfigurationService,
            ),
        );
    }

    /**
     * @brief Admin meta description must override translation fallback.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveMetaDescriptionUsesAdminValueWhenConfigured(): void
    {
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService
            ->method('resolveMetaDescriptionForLocale')
            ->with('fr')
            ->willReturn('Custom SEO tagline');

        $translator = CvPdfPlaceholderTestTranslator::create();

        $service = new SiteSeoResolverService(
            $homeCustomizationService,
            $this->createMock(CvProfileRepository::class),
            $translator,
            $this->createIdentityPlaceholderService($translator),
            $this->createMock(LocaleConfigurationService::class),
            new RequestStack(),
        );

        self::assertSame('Custom SEO tagline', $service->resolveMetaDescription('fr', 'app.meta.description'));
    }

    /**
     * @brief CV meta description must be built from public identity when admin value is empty.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveCvMetaDescriptionBuildsDynamicDescription(): void
    {
        $service = $this->createService([
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Jane Doe',
                CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE => [
                    'fr' => 'Developpeuse full-stack',
                ],
                CvPublicIdentityContract::FIELD_CITY => 'Paris',
            ],
            SkillsTreeContract::KEY => [
                'categories' => [],
            ],
        ]);

        $description = $service->resolveCvMetaDescription('fr', 'cv.meta.description');

        self::assertStringContainsString('Jane Doe', $description);
        self::assertStringContainsString('Developpeuse full-stack', $description);
        self::assertStringContainsString('Paris', $description);
    }

    /**
     * @brief Home page title must prefer sought position over generic home title.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveHomePageTitleUsesSoughtPosition(): void
    {
        $service = $this->createService([
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE => [
                    'fr' => 'Architecte cloud',
                ],
            ],
        ]);

        self::assertSame('Architecte cloud', $service->resolveHomePageTitle('fr'));
    }

    /**
     * @brief CV page title must prefix section label when requested.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveCvPageTitlePrefixesSectionLabel(): void
    {
        $service = $this->createService([
            'pageTitleByLocale' => [
                'fr' => 'CV de Jane Doe',
            ],
        ]);

        $title = $service->resolveCvPageTitle('fr', 'cv.experience.full_page_meta_title');

        self::assertStringContainsString('cv.experience.full_page_meta_title', $title);
        self::assertStringContainsString('CV de Jane Doe', $title);
    }

    /**
     * @brief Hreflang alternates must expose active locales and x-default for public CV routes.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveHreflangAlternatesForPublicCvRoute(): void
    {
        $request = Request::create('/cv/');
        $request->attributes->set('_route', 'cv_show');

        $service = $this->createService([], ['fr', 'en'], 'fr', $request);
        $alternates = $service->resolveHreflangAlternates();

        self::assertCount(3, $alternates);
        self::assertSame('fr', $alternates[0]['locale']);
        self::assertStringContainsString('/fr/cv/', $alternates[0]['url']);
        self::assertSame('x-default', $alternates[2]['locale']);
        self::assertStringContainsString('/fr/cv/', $alternates[2]['url']);
    }

    /**
     * @brief Canonical URL must use locale path prefix for public pages.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveCanonicalUrlUsesLocalePrefix(): void
    {
        $request = Request::create('/cv/');
        $request->attributes->set('_route', 'cv_show');

        $service = $this->createService([], ['fr', 'en'], 'fr', $request);

        self::assertSame('http://localhost/fr/cv/', $service->resolveCanonicalUrl('fr'));
    }

    /**
     * @brief Dedicated Open Graph image must enable summary_large_image Twitter card.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveTwitterCardTypeUsesLargeImageForDedicatedOpenGraphUpload(): void
    {
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn(new HomeCustomization());
        $homeCustomizationService->method('resolveOpenGraphImageRelativePath')->willReturn('images/home/custom/og/demo.webp');
        $homeCustomizationService->method('resolveSignatureImageRelativePath')->willReturn(null);
        $homeCustomizationService->method('resolveSiteFaviconRelativePath')->willReturn(HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH);
        $homeCustomizationService->method('isLargeFormatShareImage')->willReturn(true);

        $profile = new CvProfile('Test CV', json_encode([
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Jane Doe',
            ],
        ], JSON_THROW_ON_ERROR));

        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        $cvProfileRepository->method('findOneBy')->willReturn($profile);

        $service = new SiteSeoResolverService(
            $homeCustomizationService,
            $cvProfileRepository,
            CvPdfPlaceholderTestTranslator::create(),
            $this->createIdentityPlaceholderService(CvPdfPlaceholderTestTranslator::create()),
            $this->createMock(LocaleConfigurationService::class),
            new RequestStack(),
        );

        self::assertSame('summary_large_image', $service->resolveTwitterCardType());
        self::assertSame('images/home/custom/og/demo.webp', $service->resolveShareImageRelativePath());
    }

    /**
     * @brief Open Graph must stay disabled without both display name and share image.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testOpenGraphDisabledWithoutDisplayNameOrShareImage(): void
    {
        $service = $this->createService();

        self::assertFalse($service->isOpenGraphEnabled('fr'));
        self::assertNull($service->resolveShareImageRelativePath());
    }

    /**
     * @brief Open Graph must enable when display name and custom favicon exist.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testOpenGraphEnabledWithDisplayNameAndCustomFavicon(): void
    {
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn(new HomeCustomization());
        $homeCustomizationService->method('resolveSignatureImageRelativePath')->willReturn(null);
        $homeCustomizationService->method('resolveSiteFaviconRelativePath')->willReturn('images/home/custom/favicon.webp');
        $homeCustomizationService->method('resolveMetaDescriptionForLocale')->willReturn('');

        $profile = new CvProfile('Test CV', json_encode([
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Jane Doe',
            ],
        ], JSON_THROW_ON_ERROR));

        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        $cvProfileRepository->method('findOneBy')->willReturn($profile);

        $service = new SiteSeoResolverService(
            $homeCustomizationService,
            $cvProfileRepository,
            CvPdfPlaceholderTestTranslator::create(),
            $this->createIdentityPlaceholderService(CvPdfPlaceholderTestTranslator::create()),
            $this->createMock(LocaleConfigurationService::class),
            new RequestStack(),
        );

        self::assertTrue($service->isOpenGraphEnabled('fr'));
        self::assertSame('images/home/custom/favicon.webp', $service->resolveShareImageRelativePath());
    }

    /**
     * @brief Meta description normalization must trim, collapse whitespace, and cap length.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testNormalizeMetaDescriptionSanitizesInput(): void
    {
        self::assertSame('', SiteSeoResolverService::normalizeMetaDescription(null));
        self::assertSame('Hello world', SiteSeoResolverService::normalizeMetaDescription("  Hello\n\tworld  "));
        self::assertSame(320, mb_strlen(SiteSeoResolverService::normalizeMetaDescription(str_repeat('a', 400))));
    }

    /**
     * @brief Sitemap XML must include enriched public CV routes and metadata nodes.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testSitemapIncludesPublicCvRoutesWithMetadata(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters, int $referenceType): string {
                $paths = [
                    'app_home' => '/',
                    'cv_show' => '/cv/',
                    'cv_situation' => '/cv/situation',
                    'cv_experience_full' => '/cv/experience',
                    'cv_education_full' => '/cv/education',
                    'cv_certifications_full' => '/cv/certifications',
                    'cv_skills_full' => '/cv/skills',
                    'cv_projects_full' => '/cv/projects',
                ];

                $path = $paths[$route] ?? '/'.$route;

                return match ($referenceType) {
                    UrlGeneratorInterface::ABSOLUTE_URL => 'https://example.test'.$path,
                    UrlGeneratorInterface::RELATIVE_PATH => $path,
                    default => $path,
                };
            }
        );

        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'fr',
        ]);

        $xml = (new SiteSitemapService($urlGenerator, $localeConfigurationService))->buildXmlDocument();

        self::assertStringContainsString('<loc>https://example.test/fr/cv/</loc>', $xml);
        self::assertStringContainsString('<loc>https://example.test/en/cv/experience</loc>', $xml);
        self::assertStringContainsString('<lastmod>', $xml);
        self::assertStringContainsString('<changefreq>', $xml);
        self::assertStringContainsString('<priority>', $xml);
    }
}
