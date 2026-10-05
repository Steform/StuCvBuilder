<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Employment\CompanyCvContentMode;
use App\Entity\TrackedCompany;
use App\Service\Cv\CvSkillsCatalogAdminService;
use App\Service\Cv\CvSkillsSettingsService;
use App\Service\Employment\CompanyCvProfilePayloadService;
use App\Service\Employment\CompanyCvSkillsCustomizationService;
use App\Service\Locale\LocaleCodeNormalizer;
use App\Service\Locale\LocaleConfigurationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @brief Unit coverage for company skills customization helpers.
 */
final class CompanyCvSkillsCustomizationServiceTest extends TestCase
{
    /**
     * @brief clearAllSkillsForCompany must persist an empty catalog for a custom company.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testClearAllSkillsForCompanyPersistsEmptyCatalog(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme');
        $company->setCvContentMode(CompanyCvContentMode::CUSTOM);

        $payloadService = $this->createMock(CompanyCvProfilePayloadService::class);
        $payloadService->method('loadPayload')->willReturn([
            'skillsCatalog' => [
                'categories' => [
                    [
                        'id' => '11111111-1111-4111-8111-111111111111',
                        'labelMode' => 'canonical',
                        'canonicalLabel' => 'IT',
                        'labelsByLocale' => [],
                        'sortOrder' => 0,
                        'visibleOnPrimary' => true,
                        'layout' => ['desktop' => 12, 'tablet' => 12, 'mobile' => 12],
                        'skills' => [],
                        'subcategories' => [],
                    ],
                ],
            ],
        ]);
        $payloadService
            ->expects(self::once())
            ->method('savePayload')
            ->with(
                $company,
                self::callback(static function (array $payload): bool {
                    $catalog = $payload['skillsCatalog'] ?? null;

                    return is_array($catalog)
                        && isset($catalog['categories'])
                        && is_array($catalog['categories'])
                        && $catalog['categories'] === [];
                }),
            );

        $localeConfiguration = new LocaleConfigurationService(
            ['fr', 'en'],
            'fr',
            sys_get_temp_dir(),
            new LocaleCodeNormalizer(),
        );

        $service = new CompanyCvSkillsCustomizationService(
            $payloadService,
            $this->createMock(CvSkillsCatalogAdminService::class),
            $this->createMock(CvSkillsSettingsService::class),
            $localeConfiguration,
            $this->createMock(UrlGeneratorInterface::class),
        );

        $catalog = $service->clearAllSkillsForCompany($company);

        self::assertSame(['categories' => []], $catalog);
    }

    /**
     * @brief clearAllSkillsForCompany must reject companies that are not customized.
     *
     * @return void
     * @date 2026-10-05
     * @author Stephane H.
     */
    public function testClearAllSkillsForCompanyRequiresCustomMode(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme');
        $company->setCvContentMode(CompanyCvContentMode::SYNCED);

        $localeConfiguration = new LocaleConfigurationService(
            ['fr', 'en'],
            'fr',
            sys_get_temp_dir(),
            new LocaleCodeNormalizer(),
        );

        $service = new CompanyCvSkillsCustomizationService(
            $this->createMock(CompanyCvProfilePayloadService::class),
            $this->createMock(CvSkillsCatalogAdminService::class),
            $this->createMock(CvSkillsSettingsService::class),
            $localeConfiguration,
            $this->createMock(UrlGeneratorInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('employment.companies.cv_customization.skills.flash.not_enabled');

        $service->clearAllSkillsForCompany($company);
    }
}
