<?php

namespace App\Tests\Functional\Admin;

use App\Entity\TrustedDevice;
use App\Repository\TrustedDeviceRepository;
use App\Service\Admin\TrustedDeviceAdminService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TrustedDeviceAdminTest extends TestCase
{
    /**
     * @brief Ensure revokeAll returns removed devices count.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testRevokeAllReturnsRevokedCount(): void
    {
        $devices = [
            new TrustedDevice(10, 'fp-a', new DateTimeImmutable('+1 day')),
            new TrustedDevice(10, 'fp-b', new DateTimeImmutable('+2 day')),
        ];
        $repository = $this->createMock(TrustedDeviceRepository::class);
        $repository->method('findBy')->willReturn($devices);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('remove');
        $entityManager->expects(self::once())->method('flush');
        $service = new TrustedDeviceAdminService($repository, $entityManager);

        $count = $service->revokeAll(10);

        self::assertSame(2, $count);
    }
}
