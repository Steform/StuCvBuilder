<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Cv\CvPencilDecorationContract;
use App\Employment\CompanyCvContentMode;
use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvPublicIdentityAdminService;
use App\Service\Cv\CvPublicIdentityContract;
use App\Service\Employment\CompanyCvDataCustomizationService;
use App\Service\Employment\CompanyCvProfilePayloadService;
use App\Service\Employment\EmploymentDocumentStorageService;
use App\Service\Locale\LocaleConfigurationService;
use App\Service\Site\SiteColorsResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @brief Unit tests for per-company CV data customization.
 */
final class CompanyCvDataCustomizationServiceTest extends TestCase
{
    /**
     * @brief Synced companies cannot save company CV data.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testSaveRejectsSyncedCompany(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $service = $this->buildService();

        $result = $service->saveCvDataFromRequest($company, new Request([], [
            'page_title' => ['fr' => 'Titre'],
        ]));

        self::assertSame(
            ['employment.companies.cv_customization.cv_data.flash.not_enabled'],
            $result['flashError']
        );
        self::assertSame([], $result['flashSuccess']);
    }

    /**
     * @brief Custom companies persist identity payload onto the company clone.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testSavePersistsIdentityOnCustomCompany(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $company->setCvContentMode(CompanyCvContentMode::CUSTOM);

        $profile = new CvProfile('Acme', '{}');
        $identityPayload = [
            CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Company Name',
            CvPublicIdentityContract::FIELD_BIRTH_DATE => null,
            CvPublicIdentityContract::FIELD_CITY => 'Paris',
            CvPublicIdentityContract::FIELD_REGION => null,
            CvPublicIdentityContract::FIELD_CAREER_START_YEAR => null,
            CvPublicIdentityContract::FIELD_COUNTRY_BY_LOCALE => ['fr' => 'France'],
            CvPublicIdentityContract::FIELD_SOUGHT_POSITION_BY_LOCALE => ['fr' => 'Dev'],
            CvPublicIdentityContract::FIELD_STATUS_BY_LOCALE => ['fr' => ''],
            CvPublicIdentityContract::FIELD_TAGLINE_BY_LOCALE => ['fr' => ''],
        ];

        $payloadService = $this->createMock(CompanyCvProfilePayloadService::class);
        $payloadService->expects(self::once())->method('loadPayload')->with($company)->willReturn([
            'pageTitleByLocale' => ['fr' => 'Old'],
            CvPublicIdentityContract::KEY_ROOT => [
                CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Old',
            ],
        ]);
        $payloadService->expects(self::once())->method('savePayload')->with(
            $company,
            self::callback(static function (array $payload) use ($identityPayload): bool {
                return ($payload['pageTitleByLocale']['fr'] ?? null) === 'Titre entreprise'
                    && ($payload[CvPublicIdentityContract::KEY_ROOT] ?? null) === $identityPayload
                    && isset($payload[CvPencilDecorationContract::KEY]);
            })
        );
        $payloadService->expects(self::once())->method('requireCustomProfile')->with($company)->willReturn($profile);

        $identityAdmin = $this->createMock(CvPublicIdentityAdminService::class);
        $identityAdmin->method('extractStoredIdentityMap')->willReturn([
            CvPublicIdentityContract::FIELD_DISPLAY_NAME => 'Old',
            CvPublicIdentityContract::FIELD_BIRTH_DATE => null,
        ]);
        $identityAdmin->method('parseFromCvDataRequest')->willReturn($identityPayload);

        $localeConfig = $this->createMock(LocaleConfigurationService::class);
        $localeConfig->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr'],
            'defaultLocale' => 'fr',
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $documentStorage = $this->createMock(EmploymentDocumentStorageService::class);
        $documentStorage->expects(self::never())->method('purgeAllStampedPdfCaches');

        $service = $this->buildService(
            payloadService: $payloadService,
            identityAdmin: $identityAdmin,
            localeConfig: $localeConfig,
            documentStorage: $documentStorage,
            entityManager: $entityManager,
        );

        $request = new Request([], [
            'page_title' => ['fr' => 'Titre entreprise'],
            'cv_pencil_decoration_enabled' => '1',
            'cv_pencil_light_tone_mix_percent' => '93',
            'cv_pencil_dark_tone_mix_percent' => '90',
        ]);

        $result = $service->saveCvDataFromRequest($company, $request);

        self::assertSame([], $result['flashError']);
        self::assertSame([], $result['flashWarning']);
        self::assertSame(
            ['employment.companies.cv_customization.cv_data.flash.saved'],
            $result['flashSuccess']
        );
        self::assertSame('Titre entreprise', $profile->getTitle());
    }

    /**
     * @brief Build service with optional mocks.
     *
     * @param CompanyCvProfilePayloadService|null $payloadService Payload service mock.
     * @param CvPublicIdentityAdminService|null $identityAdmin Identity admin mock.
     * @param LocaleConfigurationService|null $localeConfig Locale config mock.
     * @param EmploymentDocumentStorageService|null $documentStorage Document storage mock.
     * @param EntityManagerInterface|null $entityManager Entity manager mock.
     * @return CompanyCvDataCustomizationService
     * @date 2026-10-05
     * @author Stephane H.
     */
    private function buildService(
        ?CompanyCvProfilePayloadService $payloadService = null,
        ?CvPublicIdentityAdminService $identityAdmin = null,
        ?LocaleConfigurationService $localeConfig = null,
        ?EmploymentDocumentStorageService $documentStorage = null,
        ?EntityManagerInterface $entityManager = null,
    ): CompanyCvDataCustomizationService {
        $locale = $localeConfig ?? $this->createMock(LocaleConfigurationService::class);
        $locale->method('getConfiguration')->willReturn([
            'activeLocales' => ['fr'],
            'defaultLocale' => 'fr',
        ]);

        $siteColors = $this->createMock(SiteColorsResolver::class);
        $siteColors->method('resolveAccentColor')->willReturn('#1e5a96');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CompanyCvDataCustomizationService(
            $payloadService ?? $this->createMock(CompanyCvProfilePayloadService::class),
            $identityAdmin ?? $this->createMock(CvPublicIdentityAdminService::class),
            $locale,
            $siteColors,
            $documentStorage ?? $this->createMock(EmploymentDocumentStorageService::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $translator,
        );
    }
}
