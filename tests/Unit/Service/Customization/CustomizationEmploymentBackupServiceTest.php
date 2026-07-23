<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Customization;

use App\Entity\CvProfile;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvVisitRepository;
use App\Repository\CvConnectionLogRepository;
use App\Repository\CvProfileRepository;
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
     * @brief Employment payload detection accepts complete format v3 or legacy v2 JSON sets.
     *
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
            $this->createMock(CvProfileRepository::class),
            $this->createMock(CompanyCvVisitRepository::class),
            $this->createMock(CvConnectionLogRepository::class),
            sys_get_temp_dir(),
        );

        $v3Entries = [];
        foreach (CustomizationBackupPaths::employmentDataPathsForVersion(3) as $path) {
            $v3Entries[$path] = '[]';
        }
        self::assertTrue($service->hasEmploymentPayload($v3Entries));
        unset($v3Entries[CustomizationBackupPaths::DATA_COMPANY_CV_PROFILES]);
        self::assertFalse($service->hasEmploymentPayload($v3Entries));

        $v2Entries = [];
        foreach (CustomizationBackupPaths::employmentDataPathsForVersion(2) as $path) {
            $v2Entries[$path] = '[]';
        }
        self::assertTrue($service->hasEmploymentPayload($v2Entries));
    }

    /**
     * @brief Export JSON entries include per-company CV profile clones.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testBuildJsonEntriesIncludesCompanyCvProfiles(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKL', 'Acme Corp', 'FR');
        $profile = new CvProfile(
            'Acme Corp',
            '{"aboutProfilePhotoPath":"images/cv/about/custom/acme.webp"}',
        );
        $profile->setTrackedCompany($company);

        $profileRepository = $this->createMock(CvProfileRepository::class);
        $profileRepository->method('findAllCompanyProfiles')->willReturn([$profile]);

        $service = new CustomizationEmploymentBackupService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentCountryRepository::class),
            $this->createMock(EmploymentPrintPlacementRepository::class),
            $this->createMock(EmploymentDocumentVariantRepository::class),
            $this->createMock(TrackedCompanyRepository::class),
            $profileRepository,
            $this->createMock(CompanyCvVisitRepository::class),
            $this->createMock(CvConnectionLogRepository::class),
            sys_get_temp_dir(),
        );

        $entries = $service->buildJsonEntries();
        self::assertArrayHasKey(CustomizationBackupPaths::DATA_COMPANY_CV_PROFILES, $entries);

        $decoded = json_decode($entries[CustomizationBackupPaths::DATA_COMPANY_CV_PROFILES], true);
        self::assertIsArray($decoded);
        self::assertSame('ABCDEFGHIJKL', $decoded[0]['companyCode'] ?? null);
        self::assertSame('Acme Corp', $decoded[0]['title'] ?? null);
    }

    /**
     * @brief Company profile payloads are exposed for public asset scanning during export.
     *
     * @return void
     * @date 2026-07-23
     * @author Stephane H.
     */
    public function testCollectCompanyProfileContentPayloadsForExport(): void
    {
        $company = new TrackedCompany('ABCDEFGHIJKM', 'Target Co', null);
        $profile = new CvProfile(
            'Target Co',
            '{"experienceEntriesByLocale":{"fr":[]}}',
        );
        $profile->setTrackedCompany($company);

        $profileRepository = $this->createMock(CvProfileRepository::class);
        $profileRepository->method('findAllCompanyProfiles')->willReturn([$profile]);

        $service = new CustomizationEmploymentBackupService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentCountryRepository::class),
            $this->createMock(EmploymentPrintPlacementRepository::class),
            $this->createMock(EmploymentDocumentVariantRepository::class),
            $this->createMock(TrackedCompanyRepository::class),
            $profileRepository,
            $this->createMock(CompanyCvVisitRepository::class),
            $this->createMock(CvConnectionLogRepository::class),
            sys_get_temp_dir(),
        );

        $payloads = $service->collectCompanyProfileContentPayloadsForExport();
        self::assertSame(['fr' => []], $payloads[0]['experienceEntriesByLocale'] ?? null);
    }
}
