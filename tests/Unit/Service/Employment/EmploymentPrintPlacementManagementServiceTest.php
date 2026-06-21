<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Employment\EmploymentDocumentKind;
use App\Entity\EmploymentPrintPlacement;
use App\Repository\EmploymentPrintPlacementRepository;
use App\Service\Employment\EmploymentPrintPlacementManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for employment print placement management.
 */
final class EmploymentPrintPlacementManagementServiceTest extends TestCase
{
    /**
     * @brief Rejects invalid square size input.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testUpdateRejectsInvalidSize(): void
    {
        $placement = new EmploymentPrintPlacement(EmploymentDocumentKind::CV, '2.50', '2.50', '2.00');
        $repository = $this->createMock(EmploymentPrintPlacementRepository::class);
        $repository->method('findOneByKind')->willReturn($placement);

        $service = new EmploymentPrintPlacementManagementService(
            $this->createMock(EntityManagerInterface::class),
            $repository,
        );

        self::assertSame(
            'employment.documents.placement.flash.size_invalid',
            $service->update(EmploymentDocumentKind::CV, '2.5', '2.5', '0'),
        );
    }

    /**
     * @brief Persists valid coordinates and size in centimeters.
     *
     * @return void
     * @date 2026-06-12
     * @author Stephane H.
     */
    public function testUpdatePersistsValidValues(): void
    {
        $placement = new EmploymentPrintPlacement(EmploymentDocumentKind::LM, '2.50', '7.00', '2.00');
        $repository = $this->createMock(EmploymentPrintPlacementRepository::class);
        $repository->method('findOneByKind')->willReturn($placement);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new EmploymentPrintPlacementManagementService($entityManager, $repository);

        self::assertNull($service->update(EmploymentDocumentKind::LM, '3,5', '12', '2,5'));
        self::assertSame('3.50', $placement->getLinkX());
        self::assertSame('12.00', $placement->getLinkY());
        self::assertSame('2.50', $placement->getSquareSizeCm());
    }
}
