<?php

namespace App\Tests\Functional\Setup;

use App\Entity\User;
use App\Service\Setup\SetupStateService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class SetupStateServiceTest extends TestCase
{
    /**
     * @brief Ensure setup lock toggles in current lifecycle.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-26
     * @author Stephane H.
     */
    public function testLockMarksSetupAsLocked(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $service = new SetupStateService($entityManager);

        self::assertFalse($service->isLocked());
        $service->lock();
        self::assertTrue($service->isLocked());
    }

    /**
     * @brief Ensure pending admin lookup only matches non-confirmed admin.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-26
     * @author Stephane H.
     */
    public function testHasPendingAdminUserByEmail(): void
    {
        $pendingAdmin = (new User())
            ->setEmail('admin@example.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setSetupConfirmed(false);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')
            ->with(['email' => 'admin@example.com'])
            ->willReturn($pendingAdmin);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $service = new SetupStateService($entityManager);

        self::assertTrue($service->hasPendingAdminUserByEmail('Admin@Example.com'));
    }
}
