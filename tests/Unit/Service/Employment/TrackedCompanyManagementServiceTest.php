<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\TrackedCompany;
use App\Employment\EmploymentDocumentKind;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\CompanyCodeGenerator;
use App\Service\Employment\EmploymentCountryList;
use App\Service\Employment\TrackedCompanyContactInput;
use App\Service\Employment\TrackedCompanyDocumentInput;
use App\Service\Employment\TrackedCompanyManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for tracked company management.
 */
final class TrackedCompanyManagementServiceTest extends TestCase
{
    /**
     * @brief Rejects invalid email on create.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreateRejectsInvalidEmail(): void
    {
        $service = $this->buildService();

        $result = $service->create(
            'Acme Corp',
            null,
            new TrackedCompanyContactInput(null, null, null, null, null, null, 'not-an-email'),
            new TrackedCompanyDocumentInput(),
        );

        self::assertNull($result['company']);
        self::assertSame('employment.companies.flash.email_invalid', $result['error']);
    }

    /**
     * @brief Rejects invalid CV document variant on create.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreateRejectsInvalidCvDocument(): void
    {
        $documentRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $documentRepository
            ->expects(self::once())
            ->method('findActiveByIdAndKind')
            ->with(99, EmploymentDocumentKind::CV)
            ->willReturn(null);

        $service = $this->buildService(documentVariantRepository: $documentRepository);

        $result = $service->create(
            'Acme Corp',
            null,
            new TrackedCompanyContactInput(null, null, null, null, null, null, null),
            new TrackedCompanyDocumentInput(99, null),
        );

        self::assertNull($result['company']);
        self::assertSame('employment.companies.flash.cv_document_invalid', $result['error']);
    }

    /**
     * @brief Persists optional contact fields on create.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreatePersistsContactFields(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(TrackedCompany::class));
        $entityManager->expects(self::once())->method('flush');

        $codeGenerator = $this->createMock(CompanyCodeGenerator::class);
        $codeGenerator->method('generate')->willReturn('Ab3xY9kLm2Qp');

        $service = new TrackedCompanyManagementService(
            $entityManager,
            $this->createMock(TrackedCompanyRepository::class),
            $codeGenerator,
            $this->createMock(EmploymentCountryList::class),
            $this->createMock(EmploymentDocumentVariantRepository::class),
        );

        $result = $service->create(
            'Acme Corp',
            null,
            new TrackedCompanyContactInput(
                'Jane Recruiter',
                '12 rue Example',
                'Bât. B',
                '75001',
                'Paris',
                '+33 1 23 45 67 89',
                'jane@example.com',
            ),
            new TrackedCompanyDocumentInput(),
        );

        self::assertNull($result['error']);
        self::assertInstanceOf(TrackedCompany::class, $result['company']);
        self::assertSame('Jane Recruiter', $result['company']->getRecruiterName());
        self::assertSame('12 rue Example', $result['company']->getAddressLine1());
        self::assertSame('Bât. B', $result['company']->getAddressLine2());
        self::assertSame('75001', $result['company']->getAddressPostalCode());
        self::assertSame('Paris', $result['company']->getAddressCity());
        self::assertSame('+33 1 23 45 67 89', $result['company']->getPhone());
        self::assertSame('jane@example.com', $result['company']->getEmail());
    }

    /**
     * @brief Clears contact fields when empty strings submitted on update.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testUpdateClearsOptionalContactFields(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme', null);
        $company->setContactDetails('Old Name', 'Old street', 'Old line 2', '00000', 'Old city', '+0000', 'old@example.com');

        $service = $this->buildService($entityManager);
        $error = $service->update(
            $company,
            'Acme Corp',
            null,
            new TrackedCompanyContactInput('', '', '', '', '', '', ''),
            new TrackedCompanyDocumentInput(),
        );

        self::assertNull($error);
        self::assertNull($company->getRecruiterName());
        self::assertNull($company->getAddressLine1());
        self::assertFalse($company->hasAddress());
        self::assertNull($company->getPhone());
        self::assertNull($company->getEmail());
    }

    /**
     * @brief Build management service with defaults.
     *
     * @param EntityManagerInterface|null $entityManager Optional entity manager mock.
     * @param EmploymentDocumentVariantRepository|null $documentVariantRepository Optional document repository mock.
     * @return TrackedCompanyManagementService
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function buildService(
        ?EntityManagerInterface $entityManager = null,
        ?EmploymentDocumentVariantRepository $documentVariantRepository = null,
    ): TrackedCompanyManagementService {
        return new TrackedCompanyManagementService(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(TrackedCompanyRepository::class),
            $this->createMock(CompanyCodeGenerator::class),
            $this->createMock(EmploymentCountryList::class),
            $documentVariantRepository ?? $this->createMock(EmploymentDocumentVariantRepository::class),
        );
    }
}
