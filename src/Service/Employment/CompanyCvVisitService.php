<?php

declare(strict_types=1);

namespace App\Service\Employment;

use App\Entity\CompanyCvVisit;
use App\Entity\CvConnectionLog;
use App\Entity\TrackedCompany;
use App\Repository\CompanyCvVisitRepository;
use App\Repository\TrackedCompanyRepository;
use App\Service\Cv\CvAccessSessionService;
use App\Employment\ConnectionKind;
use App\Service\Home\HomeCustomizationService;
use App\Service\Notification\RecruiterVisitEmailNotificationService;
use App\Service\Security\CvBotAccessService;
use App\Service\Http\RequestSessionResolver;
use App\Service\Security\CvBotAttestationService;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Official company visits (one per UTC day) and journey tracking.
 */
class CompanyCvVisitService
{
    private const VISITOR_SESSION_KEY = 'cv_company_visitor_key';

    private const GATE_ATTEMPT_DEDUP_SESSION_KEY = 'cv_company_gate_attempt_dedup';

    private const GATE_ATTEMPT_LOG_ID_SESSION_KEY = 'cv_company_gate_attempt_log_id';

    /**
     * @brief Build company CV visit service.
     *
     * @param EntityManagerInterface $entityManager ORM entity manager.
     * @param TrackedCompanyRepository $trackedCompanyRepository Company repository.
     * @param CompanyCvVisitRepository $companyCvVisitRepository Visit repository.
     * @param CompanyCodeNormalizer $companyCodeNormalizer Code normalizer.
     * @param CvAccessSessionService $cvAccessSessionService Format session helper.
     * @param CvBotAccessService $cvBotAccessService Bot access policy.
     * @param CvBotAttestationService $cvBotAttestationService Attestation helper.
     * @param CvConnectionLoggingService $cvConnectionLoggingService Connection logger.
     * @param VisitorCountryResolver $visitorCountryResolver Country resolver.
     * @param RequestSessionResolver $requestSessionResolver Safe session accessor.
     * @param HomeCustomizationService $homeCustomizationService Site customization reader.
     * @param CompanyRecruiterVisitNotificationService $companyRecruiterVisitNotificationService Notification dedup service.
     * @param RecruiterVisitEmailNotificationService $recruiterVisitEmailNotificationService Recruiter visit mailer.
     * @param UrlGeneratorInterface $urlGenerator Route URL generator.
     * @param string $kernelSecret Kernel secret for visitor key hashing.
     * @return void
     * @date 2026-06-16
     * @author Stephane H.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackedCompanyRepository $trackedCompanyRepository,
        private readonly CompanyCvVisitRepository $companyCvVisitRepository,
        private readonly CompanyCodeNormalizer $companyCodeNormalizer,
        private readonly CvAccessSessionService $cvAccessSessionService,
        private readonly CvBotAccessService $cvBotAccessService,
        private readonly CvBotAttestationService $cvBotAttestationService,
        private readonly CvConnectionLoggingService $cvConnectionLoggingService,
        private readonly VisitorCountryResolver $visitorCountryResolver,
        private readonly RequestSessionResolver $requestSessionResolver,
        private readonly HomeCustomizationService $homeCustomizationService,
        private readonly CompanyRecruiterVisitNotificationService $companyRecruiterVisitNotificationService,
        private readonly RecruiterVisitEmailNotificationService $recruiterVisitEmailNotificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $kernelSecret,
    ) {
    }

    /**
     * @brief Resolve active company for current session format code.
     *
     * @return TrackedCompany|null
     * @date 2026-06-01
     * @author Stephane H.
     */
    public function resolveActiveCompanyFromSession(): ?TrackedCompany
    {
        $code = $this->cvAccessSessionService->getActiveFormatCode();
        if ($code === '') {
            return null;
        }

        return $this->trackedCompanyRepository->findActiveByCode($code);
    }

    /**
     * @brief Record a pre-gate company consultation attempt as soon as format is known.
     *
     * @param Request $request HTTP request hitting the gate or redirected CV route.
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function recordGateNotPassedAttempt(Request $request): void
    {
        if ($this->cvBotAccessService->isAdminBypassForTracking()) {
            return;
        }

        if ($this->cvBotAccessService->isEligibleForCompanyVisit()) {
            return;
        }

        $company = $this->resolveActiveCompanyFromSession();
        if ($company === null || !$this->shouldRecordGateNotPassedAttempt($company)) {
            return;
        }

        $log = $this->cvConnectionLoggingService->log(new ConnectionLogContext(
            connectionKind: ConnectionKind::RANDOM,
            formatRaw: $company->getCode(),
            company: $company,
            ipAddress: (string) ($request->getClientIp() ?? ''),
            countryCode: $this->visitorCountryResolver->resolve($request),
            userAgent: (string) $request->headers->get('User-Agent', ''),
            gatePassed: false,
            attestationMethod: $this->cvBotAttestationService->getMethod(),
            technicalScore: $this->cvBotAttestationService->getScore(),
            countableForCompany: false,
            isAdminBypass: false,
            requestPath: $request->getPathInfo(),
            requestRoute: (string) $request->attributes->get('_route', ''),
        ));

        $this->markGateNotPassedAttemptRecorded($company, $log);
    }

    /**
     * @brief Handle official visit creation on cv_show when eligible with serialized visit writes.
     *
     * @param Request $request HTTP request.
     * @return CompanyCvVisit|null Visit when created or existing for today.
     * @date 2026-06-17
     * @author Stephane H.
     */
    public function recordOfficialVisitOnCvShow(Request $request): ?CompanyCvVisit
    {
        $company = $this->resolveActiveCompanyFromSession();
        if ($company === null) {
            return null;
        }

        if ($this->cvBotAccessService->isAdminBypassForTracking()) {
            return null;
        }

        if (!$this->cvBotAccessService->isEligibleForCompanyVisit()) {
            return null;
        }

        $notificationsEnabled = $this->homeCustomizationService
            ->getOrCreateSingleton()
            ->isRecruiterVisitNotificationEnabled();
        $visitDate = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        /** @var array{visit: CompanyCvVisit, notifyClaimed: bool} $result */
        $result = $this->entityManager->wrapInTransaction(function () use ($request, $company, $notificationsEnabled, $visitDate): array {
            $this->lockCompanyForVisitTracking($company);
            $visitResult = $this->findOrCreateVisit($request, $company);
            $visit = $visitResult['visit'];
            $this->appendJourney($visit, $request);
            $this->recordOfficialConnectionLog($request, $company, $visit);

            $notifyClaimed = false;
            if ($notificationsEnabled) {
                $notifyClaimed = $this->companyRecruiterVisitNotificationService->tryClaimDailyNotification(
                    $company,
                    $visitDate,
                    $visit,
                );
            }

            return [
                'visit' => $visit,
                'notifyClaimed' => $notifyClaimed,
            ];
        });

        if ($result['notifyClaimed']) {
            $companyId = (int) $company->getId();
            if ($companyId > 0) {
                $adminVisitsUrl = $this->urlGenerator->generate(
                    'admin_employment_companies_visits',
                    ['id' => $companyId],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
                $this->recruiterVisitEmailNotificationService->sendOfficialVisitNotification(
                    $company,
                    $result['visit'],
                    $request->getLocale(),
                    $adminVisitsUrl,
                );
            }
        }

        return $result['visit'];
    }

    /**
     * @brief Append journey step for tracked CV routes with serialized visit writes.
     *
     * @param Request $request HTTP request.
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    public function appendJourneyForRequest(Request $request): void
    {
        if ($request->attributes->get('_route') === 'cv_show') {
            return;
        }

        $company = $this->resolveActiveCompanyFromSession();
        if ($company === null || !$this->cvBotAccessService->isEligibleForCompanyVisit()) {
            return;
        }

        if ($this->cvBotAccessService->isAdminBypassForTracking()) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($request, $company): void {
            $this->lockCompanyForVisitTracking($company);
            $visit = $this->findOrCreateVisit($request, $company)['visit'];
            $this->appendJourney($visit, $request);
        });
    }

    /**
     * @brief Lock the tracked company row used as mutex for visit writes.
     *
     * @param TrackedCompany $company Active company.
     * @return void
     * @date 2026-06-11
     * @author Stephane H.
     */
    private function lockCompanyForVisitTracking(TrackedCompany $company): void
    {
        $this->entityManager->lock($company, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * @brief Find or create visit for UTC day.
     *
     * @param Request $request HTTP request.
     * @param TrackedCompany $company Active company.
     * @return array{visit: CompanyCvVisit, wasCreated: bool}
     * @date 2026-06-16
     * @author Stephane H.
     */
    private function findOrCreateVisit(Request $request, TrackedCompany $company): array
    {
        $visitDate = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $visitorKey = $this->resolveVisitorKey();
        $existing = $this->companyCvVisitRepository->findOneForDay($company, $visitDate, $visitorKey);
        if ($existing instanceof CompanyCvVisit) {
            return [
                'visit' => $existing,
                'wasCreated' => false,
            ];
        }

        $now = new DateTimeImmutable();
        $visit = new CompanyCvVisit(
            $company,
            $visitDate,
            $visitorKey,
            $now,
            (string) ($request->getClientIp() ?? null),
            $this->visitorCountryResolver->resolve($request),
        );
        $this->entityManager->persist($visit);
        $this->entityManager->flush();

        return [
            'visit' => $visit,
            'wasCreated' => true,
        ];
    }

    /**
     * @brief Append journey step to visit.
     *
     * @param CompanyCvVisit $visit Visit entity.
     * @param Request $request HTTP request.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function appendJourney(CompanyCvVisit $visit, Request $request): void
    {
        $route = (string) $request->attributes->get('_route', '');
        $path = $request->getPathInfo();
        $visit->appendJourneyStep($route, $path, new DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * @brief Persist or upgrade the official connection log after gate success.
     *
     * @param Request $request HTTP request.
     * @param TrackedCompany $company Active company.
     * @param CompanyCvVisit $visit Official visit for the UTC day.
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function recordOfficialConnectionLog(
        Request $request,
        TrackedCompany $company,
        CompanyCvVisit $visit,
    ): void {
        $preGateLog = $this->resolvePreGateConnectionLog();
        if ($preGateLog instanceof CvConnectionLog && !$preGateLog->isCountableForCompany()) {
            $this->upgradeConnectionLogToOfficial($request, $company, $visit, $preGateLog);

            return;
        }

        $this->logConnection($request, $company, $visit, true, false);
    }

    /**
     * @brief Persist connection log for cv_show handling.
     *
     * @param Request $request HTTP request.
     * @param TrackedCompany $company Company.
     * @param CompanyCvVisit|null $visit Official visit when countable.
     * @param bool $countable Whether visit is official.
     * @param bool $adminBypass Admin bypass flag.
     * @return void
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function logConnection(
        Request $request,
        TrackedCompany $company,
        ?CompanyCvVisit $visit,
        bool $countable,
        bool $adminBypass,
    ): void {
        $this->cvConnectionLoggingService->log(new ConnectionLogContext(
            connectionKind: ConnectionKind::RANDOM,
            formatRaw: $company->getCode(),
            company: $company,
            ipAddress: (string) ($request->getClientIp() ?? ''),
            countryCode: $this->visitorCountryResolver->resolve($request),
            userAgent: (string) $request->headers->get('User-Agent', ''),
            gatePassed: $this->cvBotAttestationService->hasValidGateAttestation(),
            attestationMethod: $this->cvBotAttestationService->getMethod(),
            technicalScore: $this->cvBotAttestationService->getScore(),
            countableForCompany: $countable,
            isAdminBypass: $adminBypass,
            requestPath: $request->getPathInfo(),
            requestRoute: (string) $request->attributes->get('_route', ''),
            visit: $visit,
        ));
    }

    /**
     * @brief Rewrite a pre-gate connection log into an official visit row.
     *
     * @param Request $request HTTP request.
     * @param TrackedCompany $company Active company.
     * @param CompanyCvVisit $visit Official visit entity.
     * @param CvConnectionLog $connectionLog Existing pre-gate log row.
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function upgradeConnectionLogToOfficial(
        Request $request,
        TrackedCompany $company,
        CompanyCvVisit $visit,
        CvConnectionLog $connectionLog,
    ): void {
        $connectionLog
            ->setCompany($company)
            ->setGatePassed(true)
            ->setAttestationMethod($this->cvBotAttestationService->getMethod())
            ->setTechnicalScore($this->cvBotAttestationService->getScore())
            ->setCountableForCompany(true)
            ->setIsAdminBypass(false)
            ->setRequestPath($request->getPathInfo())
            ->setRequestRoute((string) $request->attributes->get('_route', ''))
            ->setVisit($visit);

        $this->entityManager->flush();
        $this->clearGateNotPassedAttemptSessionState();
    }

    /**
     * @brief Whether a pre-gate attempt should be logged for this company today.
     *
     * @param TrackedCompany $company Active company.
     * @return bool
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function shouldRecordGateNotPassedAttempt(TrackedCompany $company): bool
    {
        $session = $this->requestSessionResolver->resolve();
        if ($session === null) {
            return true;
        }

        return $session->get(self::GATE_ATTEMPT_DEDUP_SESSION_KEY) !== $this->buildGateAttemptDedupKey($company);
    }

    /**
     * @brief Store dedup marker and pending log id in session after pre-gate logging.
     *
     * @param TrackedCompany $company Active company.
     * @param CvConnectionLog $connectionLog Persisted pre-gate log row.
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function markGateNotPassedAttemptRecorded(TrackedCompany $company, CvConnectionLog $connectionLog): void
    {
        $session = $this->requestSessionResolver->resolve();
        if ($session === null) {
            return;
        }

        $session->set(self::GATE_ATTEMPT_DEDUP_SESSION_KEY, $this->buildGateAttemptDedupKey($company));

        $logId = $connectionLog->getId();
        if ($logId !== null) {
            $session->set(self::GATE_ATTEMPT_LOG_ID_SESSION_KEY, $logId);
        }
    }

    /**
     * @brief Load pending pre-gate connection log from session when available.
     *
     * @return CvConnectionLog|null
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function resolvePreGateConnectionLog(): ?CvConnectionLog
    {
        $session = $this->requestSessionResolver->resolve();
        if ($session === null || !$session->has(self::GATE_ATTEMPT_LOG_ID_SESSION_KEY)) {
            return null;
        }

        $logId = (int) $session->get(self::GATE_ATTEMPT_LOG_ID_SESSION_KEY, 0);
        if ($logId < 1) {
            return null;
        }

        $connectionLog = $this->entityManager->find(CvConnectionLog::class, $logId);

        return $connectionLog instanceof CvConnectionLog ? $connectionLog : null;
    }

    /**
     * @brief Clear pre-gate attempt session markers after official rewrite.
     *
     * @return void
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function clearGateNotPassedAttemptSessionState(): void
    {
        $session = $this->requestSessionResolver->resolve();
        if ($session === null) {
            return;
        }

        $session->remove(self::GATE_ATTEMPT_DEDUP_SESSION_KEY);
        $session->remove(self::GATE_ATTEMPT_LOG_ID_SESSION_KEY);
    }

    /**
     * @brief Build dedup key for one company and current UTC day.
     *
     * @param TrackedCompany $company Active company.
     * @return string
     * @date 2026-06-17
     * @author Stephane H.
     */
    private function buildGateAttemptDedupKey(TrackedCompany $company): string
    {
        $visitDate = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        return $company->getCode().':'.$visitDate->format('Y-m-d');
    }

    /**
     * @brief Build stable visitor key for deduplication.
     *
     * @return string
     * @date 2026-06-01
     * @author Stephane H.
     */
    private function resolveVisitorKey(): string
    {
        $session = $this->requestSessionResolver->resolve();
        if ($session !== null) {
            $stored = $session->get(self::VISITOR_SESSION_KEY);
            if (is_string($stored) && $stored !== '') {
                return $stored;
            }

            $sessionId = $session->getId();
            $key = hash('sha256', $sessionId.$this->kernelSecret);
            $session->set(self::VISITOR_SESSION_KEY, $key);

            return $key;
        }

        return hash('sha256', 'anonymous'.$this->kernelSecret);
    }
}
