<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Cv\CompanyCvCustomizationSectionKey;
use App\Entity\CompanyCvSectionOverride;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvSectionOverrideRepository;
use App\Repository\CompanyCvVisitRepository;
use App\Repository\CvConnectionLogRepository;
use App\Repository\EmploymentCountryRepository;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\EmploymentPrintPlacementRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Customization\CustomizationBackupPaths;
use App\Service\Customization\CustomizationEmploymentBackupService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CustomizationEmploymentBackupServiceTest extends TestCase
{
    /**
     * @brief Employment payload detection requires every format v2 JSON entry.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testHasEmploymentPayloadRequiresAllDataFiles(): void
    {
        $service = new CustomizationEmploymentBackupService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentCountryRepository::class),
            $this->createMock(EmploymentPrintPlacementRepository::class),
            $this->createMock(EmploymentDocumentVariantRepository::class),
            $this->createMock(TrackedCompanyRepository::class),
            $this->createMock(CompanyCvSectionOverrideRepository::class),
            $this->createMock(CompanyCvVisitRepository::class),
            $this->createMock(CvConnectionLogRepository::class),
            sys_get_temp_dir(),
        );

        $entries = [];
        foreach (CustomizationBackupPaths::employmentDataPaths() as $path) {
            $entries[$path] = '[]';
        }

        self::assertTrue($service->hasEmploymentPayload($entries));
        unset($entries[CustomizationBackupPaths::DATA_COMPANY_CV_SECTION_OVERRIDES]);
        self::assertFalse($service->hasEmploymentPayload($entries));
    }

    /**
     * @brief Export JSON entries include per-company CV section overrides.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testBuildJsonEntriesIncludesCompanyCvSectionOverrides(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme Corp', 'FR');
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::ABOUT,
            '{"aboutProfilePhotoPath":"images/cv/about/custom/acme.webp"}',
        );

        $overrideRepository = $this->createMock(CompanyCvSectionOverrideRepository::class);
        $overrideRepository->method('findBy')->willReturn([$override]);

        $service = new CustomizationEmploymentBackupService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentCountryRepository::class),
            $this->createMock(EmploymentPrintPlacementRepository::class),
            $this->createMock(EmploymentDocumentVariantRepository::class),
            $this->createMock(TrackedCompanyRepository::class),
            $overrideRepository,
            $this->createMock(CompanyCvVisitRepository::class),
            $this->createMock(CvConnectionLogRepository::class),
            sys_get_temp_dir(),
        );

        $entries = $service->buildJsonEntries();
        self::assertArrayHasKey(CustomizationBackupPaths::DATA_COMPANY_CV_SECTION_OVERRIDES, $entries);

        $decoded = json_decode($entries[CustomizationBackupPaths::DATA_COMPANY_CV_SECTION_OVERRIDES], true);
        self::assertIsArray($decoded);
        self::assertSame('ABCDEFGHIJKL', $decoded[0]['companyCode'] ?? null);
        self::assertSame(CompanyCvCustomizationSectionKey::ABOUT, $decoded[0]['sectionKey'] ?? null);
    }

    /**
     * @brief Override payloads are exposed for public asset scanning during export.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCollectSectionOverrideContentPayloadsForExport(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKM', 'Target Co', null);
        $override = new CompanyCvSectionOverride(
            $company,
            CompanyCvCustomizationSectionKey::EXPERIENCE,
            '{"experienceEntriesByLocale":{"fr":[]}}',
        );

        $overrideRepository = $this->createMock(CompanyCvSectionOverrideRepository::class);
        $overrideRepository->method('findBy')->willReturn([$override]);

        $service = new CustomizationEmploymentBackupService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentCountryRepository::class),
            $this->createMock(EmploymentPrintPlacementRepository::class),
            $this->createMock(EmploymentDocumentVariantRepository::class),
            $this->createMock(TrackedCompanyRepository::class),
            $overrideRepository,
            $this->createMock(CompanyCvVisitRepository::class),
            $this->createMock(CvConnectionLogRepository::class),
            sys_get_temp_dir(),
        );

        $payloads = $service->collectSectionOverrideContentPayloadsForExport();
        self::assertSame(['fr' => []], $payloads[0]['experienceEntriesByLocale'] ?? null);
    }
}
