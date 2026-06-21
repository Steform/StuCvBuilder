<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\EmploymentDocumentVariant;
use App\Employment\EmploymentDocumentKind;
use App\Repository\EmploymentDocumentVariantRepository;
use App\Repository\EmploymentPrintPlacementRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Employment\EmploymentDocumentStorageService;
use App\Service\Employment\EmploymentDocumentVariantManagementService;
use App\Service\Employment\EmploymentPrintPlacementManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @brief Unit tests for employment document variant management.
 */
final class EmploymentDocumentVariantManagementServiceTest extends TestCase
{
    /**
     * @brief Rejects create when name is empty.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testCreateRejectsEmptyName(): void
    {
        $service = $this->buildService();

        $result = $service->create(EmploymentDocumentKind::CV, '   ', [], '2.50', '2.50', '2');

        self::assertNull($result['variant']);
        self::assertSame('employment.documents.flash.name_required', $result['error']);
    }

    /**
     * @brief Rejects update when no complete locale pair would remain.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testUpdateRejectsMissingLocalePair(): void
    {
        $service = $this->buildService();
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::CV, 'Standard FR');

        $error = $service->update($variant, 'Renamed', [], '2.50', '2.50', '2');

        self::assertSame('employment.documents.flash.locale_pair_required', $error);
    }

    /**
     * @brief Normalizes search query like variant names.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testNormalizeSearchQueryCollapsesWhitespace(): void
    {
        $service = $this->buildService();

        self::assertSame('acme cv', $service->normalizeSearchQuery('  Acme   CV  '));
    }

    /**
     * @brief Blocks archive when this is the only active CV.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testArchiveBlocksOnlyActiveLm(): void
    {
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::LM, 'Solo LM');
        $this->setVariantId($variant, 12);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('countActiveByKind')->with(EmploymentDocumentKind::LM)->willReturn(1);

        $service = $this->buildService(variantRepository: $variantRepository);

        self::assertSame(
            'employment.documents.flash.only_active_lm_cannot_archive',
            $service->getArchiveBlockReason($variant),
        );
    }

    public function testArchiveBlocksOnlyActiveCv(): void
    {
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::CV, 'Solo');
        $this->setVariantId($variant, 7);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('countActiveByKind')->with(EmploymentDocumentKind::CV)->willReturn(1);

        $service = $this->buildService(variantRepository: $variantRepository);

        self::assertSame(
            'employment.documents.flash.only_active_cv_cannot_archive',
            $service->getArchiveBlockReason($variant),
        );
        self::assertSame(
            'employment.documents.flash.only_active_cv_cannot_archive',
            $service->archive($variant),
        );
        self::assertFalse($variant->isArchived());
    }

    /**
     * @brief Allows archive when a replacement CV variant is available.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testArchiveAllowsCvAssignedToCompanyWhenReplacementExists(): void
    {
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::CV, 'Assigned');
        $this->setVariantId($variant, 8);

        $replacement = new EmploymentDocumentVariant(EmploymentDocumentKind::CV, 'Replacement');
        $this->setVariantId($replacement, 10);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('countActiveByKind')->with(EmploymentDocumentKind::CV)->willReturn(2);
        $variantRepository->method('findDefaultByKind')->with(EmploymentDocumentKind::CV)->willReturn($replacement);

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository
            ->expects(self::once())
            ->method('reassignActiveCompaniesDocumentVariant')
            ->with(EmploymentDocumentKind::CV, 8, 10)
            ->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');

        $service = $this->buildService($variantRepository, $trackedCompanyRepository, $entityManager);

        self::assertNull($service->getArchiveBlockReason($variant));
        self::assertNull($service->archive($variant));
        self::assertTrue($variant->isArchived());
    }

    /**
     * @brief Allows archive when a replacement LM variant is available.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testArchiveAllowsLmAssignedToCompanyWhenReplacementExists(): void
    {
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::LM, 'Assigned LM');
        $this->setVariantId($variant, 9);

        $replacement = new EmploymentDocumentVariant(EmploymentDocumentKind::LM, 'Replacement LM');
        $this->setVariantId($replacement, 11);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('countActiveByKind')->with(EmploymentDocumentKind::LM)->willReturn(2);
        $variantRepository->method('findDefaultByKind')->with(EmploymentDocumentKind::LM)->willReturn($replacement);

        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository
            ->expects(self::once())
            ->method('reassignActiveCompaniesDocumentVariant')
            ->with(EmploymentDocumentKind::LM, 9, 11)
            ->willReturn(2);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');

        $service = $this->buildService($variantRepository, $trackedCompanyRepository, $entityManager);

        self::assertNull($service->getArchiveBlockReason($variant));
        self::assertNull($service->archive($variant));
        self::assertTrue($variant->isArchived());
    }

    /**
     * @brief Unarchives archived variant and flushes once.
     *
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testUnarchiveRestoresActiveState(): void
    {
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::CV, 'Archived CV');
        $variant->archive();
        self::assertTrue($variant->isArchived());

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('countActiveByKind')->with(EmploymentDocumentKind::CV)->willReturn(2);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->buildService($variantRepository, null, $entityManager);
        $service->unarchive($variant);

        self::assertFalse($variant->isArchived());
    }

    /**
     * @brief Forces default flag when only one active CV exists after save.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testApplyDefaultKindFlagForcesSoleActiveCv(): void
    {
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::CV, 'Only one');
        $this->setVariantId($variant, 3);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('countActiveByKind')->with(EmploymentDocumentKind::CV)->willReturn(1);
        $variantRepository
            ->expects(self::once())
            ->method('clearDefaultForKindExcept')
            ->with(EmploymentDocumentKind::CV, 3);

        $service = $this->buildService($variantRepository);
        $this->invokeApplyDefaultKindFlag($service, $variant, false);

        self::assertTrue($variant->isDefault());
    }

    /**
     * @brief Forces default flag when only one active LM exists after save.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testApplyDefaultKindFlagForcesSoleActiveLm(): void
    {
        $variant = new EmploymentDocumentVariant(EmploymentDocumentKind::LM, 'Only LM');
        $this->setVariantId($variant, 4);

        $variantRepository = $this->createMock(EmploymentDocumentVariantRepository::class);
        $variantRepository->method('countActiveByKind')->with(EmploymentDocumentKind::LM)->willReturn(1);
        $variantRepository
            ->expects(self::once())
            ->method('clearDefaultForKindExcept')
            ->with(EmploymentDocumentKind::LM, 4);

        $service = $this->buildService($variantRepository);
        $this->invokeApplyDefaultKindFlag($service, $variant, false);

        self::assertTrue($variant->isDefault());
    }

    /**
     * @brief Build service with optional repository mocks.
     *
     * @param EmploymentDocumentVariantRepository|null $variantRepository Variant repository mock.
     * @param TrackedCompanyRepository|null $trackedCompanyRepository Company repository mock.
     * @param EntityManagerInterface|null $entityManager Entity manager mock.
     * @return EmploymentDocumentVariantManagementService
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function buildService(
        ?EmploymentDocumentVariantRepository $variantRepository = null,
        ?TrackedCompanyRepository $trackedCompanyRepository = null,
        ?EntityManagerInterface $entityManager = null,
    ): EmploymentDocumentVariantManagementService {
        return new EmploymentDocumentVariantManagementService(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(EmploymentDocumentStorageService::class),
            $this->createMock(EmploymentPrintPlacementRepository::class),
            new EmploymentPrintPlacementManagementService(
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(EmploymentPrintPlacementRepository::class),
            ),
            $variantRepository ?? $this->createMock(EmploymentDocumentVariantRepository::class),
            $trackedCompanyRepository ?? $this->createMock(TrackedCompanyRepository::class),
        );
    }

    /**
     * @brief Invoke private applyDefaultKindFlag for unit testing.
     *
     * @param EmploymentDocumentVariantManagementService $service Management service.
     * @param EmploymentDocumentVariant $variant Target variant.
     * @param bool $setAsDefault Admin checkbox value.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function invokeApplyDefaultKindFlag(
        EmploymentDocumentVariantManagementService $service,
        EmploymentDocumentVariant $variant,
        bool $setAsDefault,
    ): void {
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('applyDefaultKindFlag');
        $method->invoke($service, $variant, $setAsDefault);
    }

    /**
     * @brief Assign synthetic id on variant entity for tests.
     *
     * @param EmploymentDocumentVariant $variant Variant entity.
     * @param int $id Primary key.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function setVariantId(EmploymentDocumentVariant $variant, int $id): void
    {
        $reflection = new ReflectionClass($variant);
        $property = $reflection->getProperty('id');
        $property->setValue($variant, $id);
    }
}
