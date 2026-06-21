<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Site;

use App\Entity\CvProfile;
use App\Entity\HomeCustomization;
use App\Repository\CvProfileRepository;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvAgeYearsCalculator;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Cv\CvPublicIdentityPlaceholderService;
use App\Service\Cv\WebProfilesContract;
use App\Service\Employment\EmploymentDocumentStorageService;
use App\Service\Employment\EmploymentPublicDocumentPdfResolver;
use App\Service\Home\HomeCustomizationService;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\RichText\RichHtmlSanitizer;
use App\Service\Site\SiteSeoResolverService;
use App\Service\Site\SiteStructuredDataService;
use App\Tests\Support\CvPdfPlaceholderTestTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @brief Unit tests for {@see SiteStructuredDataService}.
 * @date 2026-06-21
 * @author Stephane H.
 */
final class SiteStructuredDataServiceTest extends TestCase
{
    /**
     * @brief Structured data must expose Person and ProfilePage nodes with sameAs links.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveJsonLdDocumentForCvShow(): void
    {
        $profilePayload = [
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Jane Doe',
                CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE => ['fr' => 'Architecte cloud'],
            ],
            WebProfilesContract::KEY_ENTRIES => [
                [
                    'id' => '11111111-1111-4111-8111-111111111111',
                    'platform' => 'linkedin',
                    'url' => 'https://linkedin.com/in/janedoe',
                    'label' => '',
                    'visible' => true,
                    'sortOrder' => 0,
                ],
            ],
        ];

        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn(new HomeCustomization());
        $homeCustomizationService->method('resolveOpenGraphImageRelativePath')->willReturn('images/home/custom/og/open-graph-demo.webp');
        $homeCustomizationService->method('resolveSignatureImageRelativePath')->willReturn(null);
        $homeCustomizationService->method('resolveSiteFaviconRelativePath')->willReturn(HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH);
        $homeCustomizationService->method('isLargeFormatShareImage')->willReturn(true);

        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        $cvProfileRepository->method('findOneBy')->willReturn(
            new CvProfile('Test CV', json_encode($profilePayload, JSON_THROW_ON_ERROR)),
        );

        $translator = CvPdfPlaceholderTestTranslator::create();
        $identityPlaceholderService = $this->createIdentityPlaceholderService($translator);

        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr', 'en'],
            'defaultLocale' => 'fr',
        ]);

        $request = Request::create('/cv/');
        $request->attributes->set('_route', 'cv_show');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $seoResolver = new SiteSeoResolverService(
            $homeCustomizationService,
            $cvProfileRepository,
            $translator,
            $identityPlaceholderService,
            $localeConfigurationService,
            $requestStack,
        );

        $service = new SiteStructuredDataService(
            $seoResolver,
            $cvProfileRepository,
            $identityPlaceholderService,
            $localeConfigurationService,
            $requestStack,
        );

        $json = $service->resolveJsonLdDocument('fr', 'https://example.test/fr/cv/');
        self::assertNotNull($json);

        $decoded = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://schema.org', $decoded['@context']);
        self::assertCount(2, $decoded['@graph']);
        self::assertSame('Person', $decoded['@graph'][0]['@type']);
        self::assertSame('Jane Doe', $decoded['@graph'][0]['name']);
        self::assertSame('Architecte cloud', $decoded['@graph'][0]['jobTitle']);
        self::assertSame(['https://linkedin.com/in/janedoe'], $decoded['@graph'][0]['sameAs']);
        self::assertSame('ProfilePage', $decoded['@graph'][1]['@type']);
    }

    /**
     * @brief Structured data must stay absent without configured display name.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testResolveJsonLdDocumentReturnsNullWithoutDisplayName(): void
    {
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService->method('getOrCreateSingleton')->willReturn(new HomeCustomization());
        $homeCustomizationService->method('resolveOpenGraphImageRelativePath')->willReturn(null);
        $homeCustomizationService->method('resolveSignatureImageRelativePath')->willReturn(null);
        $homeCustomizationService->method('resolveSiteFaviconRelativePath')->willReturn(HomeCustomizationService::DEFAULT_SITE_FAVICON_PATH);

        $cvProfileRepository = $this->createMock(CvProfileRepository::class);
        $cvProfileRepository->method('findOneBy')->willReturn(new CvProfile('Test CV', '{}'));

        $translator = CvPdfPlaceholderTestTranslator::create();
        $identityPlaceholderService = $this->createIdentityPlaceholderService($translator);
        $localeConfigurationService = $this->createMock(LocaleConfigurationService::class);
        $localeConfigurationService->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr'],
            'defaultLocale' => 'fr',
        ]);

        $request = Request::create('/cv/');
        $request->attributes->set('_route', 'cv_show');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $seoResolver = new SiteSeoResolverService(
            $homeCustomizationService,
            $cvProfileRepository,
            $translator,
            $identityPlaceholderService,
            $localeConfigurationService,
            $requestStack,
        );

        $service = new SiteStructuredDataService(
            $seoResolver,
            $cvProfileRepository,
            $identityPlaceholderService,
            $localeConfigurationService,
            $requestStack,
        );

        self::assertNull($service->resolveJsonLdDocument('fr', 'https://example.test/fr/cv/'));
    }

    /**
     * @brief Build placeholder service without mocking final employment PDF resolver.
     *
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator Translator used by the placeholder service.
     * @return CvPublicIdentityPlaceholderService
     * @date 2026-06-21
     * @author Stephane H.
     */
    private function createIdentityPlaceholderService($translator): CvPublicIdentityPlaceholderService
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
}
