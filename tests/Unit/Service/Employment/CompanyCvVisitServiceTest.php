<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Employment;

use App\Entity\CompanyCvVisit;
use App\Entity\CvConnectionLog;
use App\Entity\HomeCustomization;
use App\Entity\TrackedCompany;
use App\Employment\ConnectionKind;
use App\Repository\CompanyCvVisitRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvAccessSessionService;
use App\Service\Employment\CompanyCodeNormalizer;
use App\Service\Employment\CompanyCvVisitService;
use App\Service\Employment\CompanyRecruiterVisitNotificationService;
use App\Service\Employment\CvConnectionLoggingService;
use App\Service\Employment\VisitorCountryResolver;
use App\Service\Home\HomeCustomizationService;
use App\Service\Http\RequestSessionResolver;
use App\Service\Notification\RecruiterVisitEmailNotificationService;
use App\Service\Security\CvBotAccessService;
use App\Service\Security\CvBotAttestationService;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @brief Unit tests for company CV visit tracking.
 */
final class CompanyCvVisitServiceTest extends TestCase
{
    /**
     * @brief Official visit creation locks the company before writing visit data.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testRecordOfficialVisitLocksCompanyForSerializedWrites(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $entityManager
            ->expects(self::once())
            ->method('lock')
            ->with($company, LockMode::PESSIMISTIC_WRITE);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(CompanyCvVisit::class));
        $entityManager
            ->expects(self::exactly(2))
            ->method('flush');

        $visitRepository = $this->createMock(CompanyCvVisitRepository::class);
        $visitRepository
            ->expects(self::once())
            ->method('findOneForDay')
            ->with($company, self::isInstanceOf(DateTimeImmutable::class), hash('sha256', 'anonymoustest-secret'))
            ->willReturn(null);

        $logger = $this->createMock(CvConnectionLoggingService::class);
        $logger
            ->expects(self::once())
            ->method('log')
            ->willReturn(new CvConnectionLog(ConnectionKind::RANDOM, new DateTimeImmutable()));

        $request = Request::create('/cv/', 'GET');
        $request->attributes->set('_route', 'cv_show');

        $service = $this->buildService($company, $entityManager, $visitRepository, $logger);

        $visit = $service->recordOfficialVisitOnCvShow($request);

        self::assertInstanceOf(CompanyCvVisit::class, $visit);
        self::assertSame('cv_show', $visit->getJourneyJson()[0]['route'] ?? null);
    }

    /**
     * @brief Journey append locks the company before updating the existing visit JSON.
     *
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function testAppendJourneyLocksCompanyBeforeUpdatingVisit(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $visit = new CompanyCvVisit(
            $company,
            new DateTimeImmutable('today'),
            hash('sha256', 'anonymoustest-secret'),
            new DateTimeImmutable('2026-06-11 10:00:00'),
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $entityManager
            ->expects(self::once())
            ->method('lock')
            ->with($company, LockMode::PESSIMISTIC_WRITE);
        $entityManager
            ->expects(self::never())
            ->method('persist');
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $visitRepository = $this->createMock(CompanyCvVisitRepository::class);
        $visitRepository
            ->expects(self::once())
            ->method('findOneForDay')
            ->with($company, self::isInstanceOf(DateTimeImmutable::class), hash('sha256', 'anonymoustest-secret'))
            ->willReturn($visit);

        $request = Request::create('/cv/experience', 'GET');
        $request->attributes->set('_route', 'cv_experience');

        $service = $this->buildService($company, $entityManager, $visitRepository);
        $service->appendJourneyForRequest($request);

        self::assertSame('cv_experience', $visit->getJourneyJson()[0]['route'] ?? null);
    }

    /**
     * @brief Pre-gate company attempt is logged before recruiter passes the gate.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testRecordGateNotPassedAttemptLogsKnownCompanyBeforeGate(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $logger = $this->createMock(CvConnectionLoggingService::class);
        $logger
            ->expects(self::once())
            ->method('log')
            ->with(self::callback(static function (object $context): bool {
                return $context instanceof \App\Service\Employment\ConnectionLogContext
                    && $context->gatePassed === false
                    && $context->countableForCompany === false
                    && $context->formatRaw === 'Ab3xY9kLm2Qp';
            }))
            ->willReturnCallback(static function (): CvConnectionLog {
                $log = new CvConnectionLog(ConnectionKind::RANDOM, new DateTimeImmutable());
                $reflection = new \ReflectionProperty($log, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($log, 42);

                return $log;
            });

        $session = new Session(new MockArraySessionStorage());
        $sessionResolver = $this->createMock(RequestSessionResolver::class);
        $sessionResolver->method('resolve')->willReturn($session);

        $botAccessService = $this->createMock(CvBotAccessService::class);
        $botAccessService->method('isAdminBypassForTracking')->willReturn(false);
        $botAccessService->method('isEligibleForCompanyVisit')->willReturn(false);

        $service = $this->buildService(
            $company,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(CompanyCvVisitRepository::class),
            $logger,
            $sessionResolver,
            $botAccessService,
        );

        $request = Request::create('/cv/access?format=Ab3xY9kLm2Qp', 'GET');
        $request->setSession($session);
        $request->attributes->set('_route', 'cv_access');

        $service->recordGateNotPassedAttempt($request);
        $service->recordGateNotPassedAttempt($request);

        self::assertSame(42, $session->get('cv_company_gate_attempt_log_id'));
    }

    /**
     * @brief Official visit rewrites the pre-gate connection log instead of duplicating it.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function testRecordOfficialVisitUpgradesPreGateConnectionLog(): void
    {
        $company = new TrackedCompany('Ab3xY9kLm2Qp', 'Acme');
        $preGateLog = new CvConnectionLog(ConnectionKind::RANDOM, new DateTimeImmutable());
        $reflection = new \ReflectionProperty($preGateLog, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($preGateLog, 77);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $entityManager
            ->expects(self::once())
            ->method('lock')
            ->with($company, LockMode::PESSIMISTIC_WRITE);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(CompanyCvVisit::class));
        $entityManager
            ->expects(self::exactly(3))
            ->method('flush');
        $entityManager
            ->expects(self::once())
            ->method('find')
            ->with(CvConnectionLog::class, 77)
            ->willReturn($preGateLog);

        $visitRepository = $this->createMock(CompanyCvVisitRepository::class);
        $visitRepository
            ->method('findOneForDay')
            ->willReturn(null);

        $logger = $this->createMock(CvConnectionLoggingService::class);
        $logger
            ->expects(self::never())
            ->method('log');

        $session = new Session(new MockArraySessionStorage());
        $session->set('cv_company_gate_attempt_log_id', 77);
        $sessionResolver = $this->createMock(RequestSessionResolver::class);
        $sessionResolver->method('resolve')->willReturn($session);

        $request = Request::create('/cv/', 'GET');
        $request->setSession($session);
        $request->attributes->set('_route', 'cv_show');

        $service = $this->buildService(
            $company,
            $entityManager,
            $visitRepository,
            $logger,
            $sessionResolver,
        );

        $visit = $service->recordOfficialVisitOnCvShow($request);

        self::assertInstanceOf(CompanyCvVisit::class, $visit);
        self::assertTrue($preGateLog->isGatePassed());
        self::assertTrue($preGateLog->isCountableForCompany());
        self::assertSame($visit, $preGateLog->getVisit());
        self::assertFalse($session->has('cv_company_gate_attempt_log_id'));
    }

    /**
     * @brief Build visit service with eligible tracking defaults.
     *
     * @param TrackedCompany $company Active company returned from session code.
     * @param EntityManagerInterface&MockObject $entityManager ORM entity manager mock.
     * @param CompanyCvVisitRepository&MockObject $visitRepository Visit repository mock.
     * @param CvConnectionLoggingService&MockObject|null $logger Optional connection logger mock.
     * @param RequestSessionResolver&MockObject|null $sessionResolver Optional session resolver mock.
     * @param CvBotAccessService&MockObject|null $botAccessService Optional bot access mock.
     * @return CompanyCvVisitService
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function buildService(
        TrackedCompany $company,
        EntityManagerInterface&MockObject $entityManager,
        CompanyCvVisitRepository&MockObject $visitRepository,
        ?CvConnectionLoggingService $logger = null,
        ?RequestSessionResolver $sessionResolver = null,
        ?CvBotAccessService $botAccessService = null,
    ): CompanyCvVisitService {
        $trackedCompanyRepository = $this->createMock(TrackedCompanyRepository::class);
        $trackedCompanyRepository
            ->method('findActiveByCode')
            ->with($company->getCode())
            ->willReturn($company);

        $accessSessionService = $this->createMock(CvAccessSessionService::class);
        $accessSessionService
            ->method('getActiveFormatCode')
            ->willReturn($company->getCode());

        $botAccessService = $botAccessService ?? $this->createConfiguredBotAccessService();
        $sessionResolver = $sessionResolver ?? $this->createConfiguredSessionResolver();

        $visitorCountryResolver = $this->createMock(VisitorCountryResolver::class);
        $visitorCountryResolver
            ->method('resolve')
            ->willReturn('FR');

        $homeCustomization = new HomeCustomization();
        $homeCustomizationService = $this->createMock(HomeCustomizationService::class);
        $homeCustomizationService
            ->method('getOrCreateSingleton')
            ->willReturn($homeCustomization);

        $notificationDedupService = $this->createMock(CompanyRecruiterVisitNotificationService::class);
        $notificationDedupService
            ->expects(self::never())
            ->method('tryClaimDailyNotification');

        $recruiterVisitEmailNotificationService = $this->createMock(RecruiterVisitEmailNotificationService::class);
        $recruiterVisitEmailNotificationService
            ->expects(self::never())
            ->method('sendOfficialVisitNotification');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        return new CompanyCvVisitService(
            $entityManager,
            $trackedCompanyRepository,
            $visitRepository,
            new CompanyCodeNormalizer(),
            $accessSessionService,
            $botAccessService,
            $this->createMock(CvBotAttestationService::class),
            $logger ?? $this->createMock(CvConnectionLoggingService::class),
            $visitorCountryResolver,
            $sessionResolver,
            $homeCustomizationService,
            $notificationDedupService,
            $recruiterVisitEmailNotificationService,
            $urlGenerator,
            'test-secret',
        );
    }

    /**
     * @brief Create bot access mock eligible for official visit tracking.
     *
     * @param void No input parameter.
     * @return CvBotAccessService&MockObject
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function createConfiguredBotAccessService(): CvBotAccessService&MockObject
    {
        $botAccessService = $this->createMock(CvBotAccessService::class);
        $botAccessService
            ->method('isAdminBypassForTracking')
            ->willReturn(false);
        $botAccessService
            ->method('isEligibleForCompanyVisit')
            ->willReturn(true);

        return $botAccessService;
    }

    /**
     * @brief Create session resolver mock without active session.
     *
     * @param void No input parameter.
     * @return RequestSessionResolver&MockObject
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function createConfiguredSessionResolver(): RequestSessionResolver&MockObject
    {
        $sessionResolver = $this->createMock(RequestSessionResolver::class);
        $sessionResolver
            ->method('resolve')
            ->willReturn(null);

        return $sessionResolver;
    }
}
