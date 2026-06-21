<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\CompanyRecruiterVisitNotification;
use App\Entity\TrackedCompany;
use App\Repository\CompanyRecruiterVisitNotificationRepository;
use App\Service\Employment\CompanyRecruiterVisitNotificationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @brief Unit tests for {@see CompanyRecruiterVisitNotificationService}.
 */
final class CompanyRecruiterVisitNotificationServiceTest extends TestCase
{
    /**
     * @brief First daily claim succeeds and persists dedup row.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testTryClaimDailyNotificationReturnsTrueOnFirstClaim(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $notificationDate = new DateTimeImmutable('2026-06-16');

        $repository = $this->createMock(CompanyRecruiterVisitNotificationRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneForDay')
            ->with($company, $notificationDate)
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(CompanyRecruiterVisitNotification::class));
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $service = new CompanyRecruiterVisitNotificationService($entityManager, $repository);

        self::assertTrue($service->tryClaimDailyNotification($company, $notificationDate));
    }

    /**
     * @brief Second daily claim is rejected when row already exists.
     *
     * @param void No input parameter.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function testTryClaimDailyNotificationReturnsFalseWhenAlreadyClaimed(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $notificationDate = new DateTimeImmutable('2026-06-16');
        $existing = new CompanyRecruiterVisitNotification($company, $notificationDate, new DateTimeImmutable());

        $repository = $this->createMock(CompanyRecruiterVisitNotificationRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneForDay')
            ->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new CompanyRecruiterVisitNotificationService($entityManager, $repository);

        self::assertFalse($service->tryClaimDailyNotification($company, $notificationDate));
    }
}
