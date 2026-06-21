<?php

namespace App\Tests\Functional\Security;

use App\Entity\TrustedDevice;
use App\Repository\TrustedDeviceRepository;
use App\Service\Auth\TrustedDeviceService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TrustedDeviceServiceTest extends TestCase
{
    /**
     * @brief Ensure device is trusted when active record exists.
     * @param void No input parameter.
     * @return void
     * @date 2026-04-23
     * @author Stephane H.
     */
    public function testIsTrustedDeviceReturnsTrueWhenRecordExists(): void
    {
        $repository = $this->createMock(TrustedDeviceRepository::class);
        $repository->method('findActiveByUserAndFingerprint')
            ->willReturn(new TrustedDevice(10, 'fingerprint', new DateTimeImmutable('+1 day')));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new TrustedDeviceService($repository, $entityManager, 'test-secret');

        $request = Request::create('/');
        $request->headers->set('User-Agent', 'UA');
        $request->headers->set('Accept-Language', 'fr');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        self::assertTrue($service->isTrustedDevice(10, $request));
    }
}
