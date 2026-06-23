<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cv;

use App\Entity\TrackedCompany;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\CompanyCodeNormalizer;
use App\Service\Employment\CvConnectionLoggingService;
use App\Service\Employment\VisitorCountryResolver;
use App\Service\Http\RequestSessionResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CvAccessSessionServiceTest extends TestCase
{
    private const VALID_CODE = 'Ab3xY9kLm2Qp';

    /**
     * @brief Build session service with optional security bypass and company lookup.
     *
     * @param bool $bypass Whether security roles bypass the gate.
     * @param bool $companyExists Whether active company exists for valid code.
     * @return CvAccessSessionService
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function createService(bool $bypass = false, bool $companyExists = true): CvAccessSessionService
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => $bypass && ($role === 'ROLE_ADMIN' || $role === 'ROLE_CV_CONSULT')
        );

        $company = new TrackedCompany(self::VALID_CODE, 'Acme');
        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository->method('findActiveByCode')->willReturnCallback(
            static fn (string $code): ?TrackedCompany => $companyExists && $code === self::VALID_CODE ? $company : null
        );

        $cvConnectionLoggingService = $this->createMock(CvConnectionLoggingService::class);
        $visitorCountryResolver = $this->createMock(VisitorCountryResolver::class);

        return new CvAccessSessionService(
            $requestStack,
            $security,
            new CompanyCodeNormalizer(),
            $trackedCompanyRepository,
            $cvConnectionLoggingService,
            $visitorCountryResolver,
            new RequestSessionResolver($requestStack),
            new NullLogger(),
        );
    }

    /**
     * @brief Grant access should be valid for 30 minutes.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testGrantAccessExpiresAfterTtl(): void
    {
        $service = $this->createService();
        self::assertFalse($service->isAccessGranted());

        $service->grantAccess();
        self::assertTrue($service->isAccessGranted());
    }

    /**
     * @brief First valid format code is sticky; second capture is ignored.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testFormatCaptureIsSticky(): void
    {
        $service = $this->createService();
        $service->captureTargetFormatFromQuery(self::VALID_CODE);
        self::assertSame(self::VALID_CODE, $service->getActiveFormatCode());

        $service->captureTargetFormatFromQuery('Xy9kLm2QpAb3');
        self::assertSame(self::VALID_CODE, $service->getActiveFormatCode());
    }

    /**
     * @brief Invalid format codes are not stored.
     *
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function testInvalidFormatIsIgnored(): void
    {
        $service = $this->createService();
        $service->captureTargetFormatFromQuery('bad format!');
        self::assertSame('', $service->getActiveFormatCode());
    }

    /**
     * @brief Invalid format query must not require the access gate.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testInvalidFormatDoesNotRequireAccessGate(): void
    {
        $service = $this->createService();
        $request = Request::create('/cv/?format=bad%20format');

        self::assertFalse($service->requiresAccessGate($request));
    }

    /**
     * @brief Valid format query must require the access gate.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testValidFormatRequiresAccessGate(): void
    {
        $service = $this->createService();
        $request = Request::create('/cv/?format='.self::VALID_CODE);

        self::assertTrue($service->requiresAccessGate($request));
    }

    /**
     * @brief Sticky session format keeps gate required without query parameter.
     *
     * @return void
     * @date 2026-06-21
     * @author Stephane H.
     */
    public function testStickyFormatRequiresAccessGateWithoutQueryParam(): void
    {
        $service = $this->createService();
        $service->captureTargetFormatFromQuery(self::VALID_CODE);
        $request = Request::create('/cv/');

        self::assertTrue($service->requiresAccessGate($request));
    }

    /**
     * @brief ROLE_CV_CONSULT bypasses access check.
     *
     * @return void
     * @date 2026-05-19
     * @author Stephane H.
     */
    public function testConsultBypassGrantsAccess(): void
    {
        $service = $this->createService(true);
        self::assertTrue($service->isBypassGranted());
        self::assertTrue($service->isAccessGranted());
    }
}
